<?php

namespace App\Controller;

use App\Entity\Article;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BlogController extends AbstractController
{
    /**
     * @Route("/blog", name="blog")
     */
    public function index()
    {

        //pour pouvoir dialoguer avec la bdd via Doctrine (ORM)
        //il faut faire appel au Repository en charge de l'entité à manipuler
        //pour récup un Repository il faut utiliser la méthode getRepository() de Doctrine
        $articleRepository = $this->getDoctrine()->getRepository(Article::class);
        //on demande ensuite a l'articleRepository de faire une requête pour nous
        //et d'aller checher nos articles en bdd
        $articles = $articleRepository->findAll();

        //on envoie ensuite le tableau au template
        return $this->render('blog/index.html.twig', [
            'articles' => $articles,
        ]);
    }
    /**
    * @Route("/blog/article/{id}", name="article_view", requirements={"id"="\d+" } )
     */
    public function view($id){
        $articleRepository = $this->getDoctrine()->getRepository
        (Article::class);
        $article = $articleRepository->find($id);
        if(is_null($article)){
            //le mot clef throw() permet de lancer une exception (erruer)
            // que symfony ser en charge de traier. L'exception de type NotFoundException
            // indique à symfony d'envoyer une page 404 not found
           throw $this->createNotFoundException('Article not found');
        }

        return $this->render('blog/view.html.twig', [
            'article'=>$article
        ]);
    }
    /**
     * @Route("/blog/article/delete/{id}", name="article_delete", requirements={"id"="\d+"})
     */
    public function delete($id){

         //denyAccessUnlessGranted redirige le user si il n'est pas connecté
         $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

         //une fois qu'on est sûr que quelqu'un est connecté
         //on récupère le user 
         $user = $this->getUser();

        //on va récupérer l'article à supprimer via le repository
        $articleRepository=$this->getDoctrine()->getRepository(Article::class);
        $article = $articleRepository->find($id);

        //si cet article n'existe pas on envoie 404
        if(is_null($article)){
            throw $this->createNotFoundException('Article not found');
        }

        if($user->getId() != $article->getAuthor()->getId()){
            return $this->redirectToRoute('article_view', [
                'id'=>$article->getId()
                ]);
            }

            //avant de supprimer l'article on supprime son image
            unlink($this->getParameter('image_dir'). '/' . $article->getImage());

        //pour effectuer une action de suppression via Doctrine
        //il faut demander à l'EntityManager, le composant en charge des entités
        // d'arrêter de s'occuper de l'entité qu'on a récupéré
        $entityManager = $this->getDoctrine()->getManager();
        //une fois l'entityManager récupéré, on lui demande de remove notre article
        $entityManager->remove($article);
        $entityManager->flush();

        // une fois le travail effectué, on redirige l'utilisateur vers l'acceuil
        return $this->redirectToRoute('blog');

    }

    /**
     * @Route("/blog/article/add", name="article_add")
     */

    public function add(Request $request){
        //on refuse l'accès aux utilisateurs non connectés
        //IS_AUTHENTICATED_REMEMBERED permet d'autoriser l'accès aux utilisateur connectés
        //même via un cookie "se souvenir de moi"
        //pour forcer une authentification complète on eput utiliser IS_AUTHENTICATED_FULLY
        //on pourrait aussi utiliser ROLE_USER en paramètre
        //denyAccessUnlessGranted redirige le user si il n'est pas connecté
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');

        //une fois qu'on est sûr que quelqu'un est connecté
        //on récupère le user 
        $user = $this->getUser();

        
        //il faut desormais créer une entité à enregistrer
        $article = new Article();
        $article-> setCreatedAt(new \DateTime());
        $article -> setAuthor($user);

        //on construit ensuite notre formulaire de creation d'article à l'aide de FormBuilder
        //FormBuilder aura besoin de notre entité pour y stocker les valeurs de notre form
        $form = $this->createFormBuilder($article)
                ->add('title', TextType::class)
                ->add('content', TextareaType::class)
                ->add('image', FileType::class, ['mapped' => false])
                ->add('submit', SubmitType::class, ['label'=> 'Post Article'])
                ->getForm();
        
        //on demande au formulaire de traiter la requête HTTP
        //l'objet requête contient la méthode ainsi que les paramètres et en tête de la requête HTTP
        //ayant mené jusqu'à ce traitement
        $form->handleRequest($request);
        //si le formulaire a été envoyé et si le fomulaire est valide
        if ($form->isSubmitted() && $form->isValid()){
            //gestion du fichier image uploadé
            //on commence par recup les données de l'image
            $imageFile = $form->get('image')->getData();

            //pour pouvoir stocker l'image sur notre serveur on doit lui donner un nom unique
            //on doit donc générer un nom le plus unique possible
            $uniqueName = md5(uniqid());//génére un id unique haché en md5 pour avoir des noms de longueur fixe
            //on accroche ensuite l'extension du fichier à son nouveau nom
            $filename =$uniqueName . '.' .$imageFile->guessExtension();

            //on va maintenant essayer d'enregistrer l'image sur notre serveur
            try{
                //getParameter permet d'aller chercher un paramètre contenu dans
                //la catégorie parameters dans config/services.yaml
                $imageFile->move($this->getParameter('image_dir'), $filename);
    
                //on enregistre le nom du fichier dans notre entité article
                $article->setImage($filename);

            }catch(FileException $e){
                throw $e; //TODO gérer l'erreur de fichier graçieusement
            }
            

            //pour ajouter une entité en base via Doctrine
            //il faut faire appel a l'entityManager
            $entityManager = $this->getDoctrine()->getManager();
    
            //une foie notre entité créée et ses propriétés remplies
            //on demande à Doctrine de "suivre" cette entité à l'aide de persist
            $entityManager->persist($article); //à cet instant aucune requête n'a été lancée
            //on lui demande ensuite de valider ces changements (INSERT INTO)
            $entityManager->flush();
            
                        //on peut ensuite rediriger vers l'index
                    return $this->redirectToRoute('blog');

        }




        return $this->render('blog/add.html.twig', ['add_form' => $form->createView()]);
    }

    /**
     * @Route("/blog/article/edit/{id}", name="article_edit", requirements={"id"="\d+"})
     */

    //on récupère notre article via son Repository
    public function update($id, Request $request){
        //refuser l'accès au utilisateur non connectés
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_REMEMBERED');
        
        $user = $this->getUser();
        
        $article = $this->getDoctrine()->getRepository(Article::class)->find($id);
        
        if(!$article){
            throw $this->createNotFoundException(
                'Article not found'
            );
        }
        
        //si l'utilisateur n'est pas l'auteur de l'article on renvoie vers la vue.
        if($user->getId() != $article->getAuthor()->getId()){
            return $this->redirectToRoute('article_view', [
                'id'=>$article->getId()
                ]);
            }
            
            //une fois qu'on est sûrs que l'article est bien récupéré
            //on peut le modifier
        $form = $this->createFormBuilder($article)
        ->add('title', TextType::class)
        ->add('content', TextareaType::class)
        ->add('image', FileType::class, ['mapped' => false, 'required' => false])
        ->add('submit', SubmitType::class, ['label'=> 'Post changes'])
        ->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
             //gestion du fichier image uploadé
            //on commence par recup les données de l'image
            $imageFile = $form->get('image')->getData();
            if($imageFile){
                //pour pouvoir stocker l'image sur notre serveur on doit lui donner un nom unique
                //on doit donc générer un nom le plus unique possible
                $uniqueName = md5(uniqid());//génére un id unique haché en md5 pour avoir des noms de longueur fixe
                //on accroche ensuite l'extension du fichier à son nouveau nom
                $filename =$uniqueName . '.' .$imageFile->guessExtension();
    
                //on va maintenant essayer d'enregistrer l'image sur notre serveur
                try{
                    //getParameter permet d'aller chercher un paramètre contenu dans
                    //la catégorie parameters dans config/services.yaml
                    $imageFile->move($this->getParameter('image_dir'), $filename);
        
                    //on enregistre le nom du fichier dans notre entité article
                    $article->setImage($filename);
    
                }catch(FileException $e){
                    throw $e; //TODO gérer l'erreur de fichier graçieusement
                }
                

            }

            $article->setEditedAt(new \DateTime());
            
            //une fois la modification faite on demande à l'entityManager de valider
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('article_view', [
                'id'=>$article->getId() ]);
           
        }
        return $this->render('blog/edit.html.twig', ['edit_form' => $form->createView()]);

    }

}
