<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WellKnown extends AbstractController
{
	#[Route("/.well-known/webfinger", name: "wk_webfinger")]
	public function wk_webfinger(): JsonResponse {

		//	Create User's WebFinger
		$feature = array(
			"subject" => "acct:edent_location@{$_SERVER['SERVER_NAME']}",
			"links"   => array(
				array(
					"rel"  => "self",
					"type" => "application/activity+json",
					"href" => "https://{$_SERVER['SERVER_NAME']}/edent_location"
				),
				array(
					"rel"  => "http://webfinger.net/rel/avatar",
					"type" => "image/jpeg",
					"href" => "https://{$_SERVER['SERVER_NAME']}/icon.jpg"
				)
			),
		);
	
		//	Render the page
		$response = new JsonResponse($feature);	
		return $response;
	}
}
