<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;


class HomeController extends AbstractController
{
    /**
     * @Route("/hello")
     */
    public function hello()
    {
        $message = "Jafar, j'étouffe!!";
        
        return $this->render('hello.html.twig',
        [
            'message' => $message
        ]);

    }
}