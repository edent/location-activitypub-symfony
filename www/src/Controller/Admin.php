<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class Admin extends AbstractController
{
	#[Route('/', name: 'base')]
	public function base(): Response {
		return $this->render('index.html.twig', [
			"username" => $_ENV['USERNAME'],
			"domain"   => $_SERVER['SERVER_NAME']
		]);
	}

	#[Route("/new", name: "new")]
	public function new(): Response {
		return $this->render('new.html.twig', [
			"password" => $_ENV["API_PASSWORD"]
		]);
	}

	#[Route("/logs", name: "logs")]
	public function logs(): Response {

		//	Get all log files
		$logs = glob( "logs/" . "*.json" );

		//	Newest first
		$logs = array_reverse( $logs );
			
		//	Create an ordered list
		$features = [];

		//	Loop through them all
		foreach ($logs as $log) {
			//	Get contents of the file
			$features[] = $log ;
		}

		//	Render the page
		return $this->render('logs.html.twig', [
			"logs" => $logs,
		]);
	}
}
