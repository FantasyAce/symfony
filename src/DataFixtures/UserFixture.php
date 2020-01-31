<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixture extends Fixture
{
    //on stockera l'objet UserPasswordEncoder dans cette propriété
    private $encoder;

    //on utilise l'injection de dépendence de symfony pour récuperer une instance de UserPasswordEncoderInterface
    public function __construct(UserPasswordEncoderInterface $encoderInterface){
        //on profite du constructeur pour initialiser le passwordEncoder et le stocker dans notre propriété
        $this->encoder = $encoderInterface;
    }

    public function load(ObjectManager $manager)
    {
        
            for ($i=1; $i <= 25; $i++){
                $user = new User;
                $user->setUsername('Test'.$i);

                //on utilise enfin le UserPasswordEncoder fourni par symfony pour hacher le mdp
                //la méthode encodePassword demande l'objet user pour générer le salt, ainsi que le mdp à hacher
                $password = $this->encoder->encodePassword($user, 'motdepasse'.$i);
                $user->setPassword($password);
                //une fois notre entité User correctement hydraté on ajoute la réference pour utilisation
                //dans d'autres fixtures
                $this->addReference('Author'.$i, $user);
                //enfin on persiste l'utilisateur via doctrine
                $manager->persist($user);
            }
        
            $manager->flush();
    }
}
