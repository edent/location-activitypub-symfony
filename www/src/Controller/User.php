<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class User extends AbstractController
{
	#[Route("/edent_location", name: "user")]
	public function user(): JsonResponse {

		//	Create User's Profile
		$feature = array(
			"@context" => array(
				"https://www.w3.org/ns/activitystreams",
				"https://w3id.org/security/v1"
			),
			"id"        => "https://location.edent.tel/edent_location",
			"type"      => "Person",
			"following" => "https://location.edent.tel/following",
			"followers" => "https://location.edent.tel/followers",
			"inbox"     => "https://location.edent.tel/inbox",
			"preferredUsername" => "edent_location",
			"name"      => "Terence Eden's location",
			"summary"   => "Where @edent is.",
			"url"       => "https://location.edent.tel/edent_location",
			"manuallyApprovesFollowers" => true,
			"discoverable" => true,
			"indexable"    => true,
			"published" => "2000-01-01T00:00:00Z",
			"icon" => array(
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://location.edent.tel/icon.jpg"
			),
			"image" => array(
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://location.edent.tel/image.jpg"
			),
			"publicKey" => array(
				"id"           => "https://location.edent.tel/edent_location#main-key",
				"owner"        => "https://locaiton.edent.tel/edent_location",
				"publicKeyPem" => $_ENV["PUBLIC_KEY"]
			)
		);

		//	Render the page
		$response = new JsonResponse($feature);	
		return $response;
	}

	#[Route("/following", name: "following")]
	public function following(): JsonResponse {

		//	Create User's Profile
		$feature = array(
			"@context"   => "https://www.w3.org/ns/activitystreams",
			"id"         => "https://location.edent.tel/following",
			"type"       => "OrderedCollection",
			"totalItems" => 0,
			"first"      => "https://location.edent.tel/following_accts"
		);
		//	Render the page
		$response = new JsonResponse($feature);
		return $response;
	}

	#[Route("/followers", name: "followers")]
	public function followers(): JsonResponse {

		//	Create User's Profile
		$feature = array(
				"@context"   => "https://www.w3.org/ns/activitystreams",
				"id"         => "https://location.edent.tel/followers",
				"type"       => "OrderedCollection",
				"totalItems" => 0,
				"first"      => "https://location.edent.tel/follower_accts"
		);
		//	Render the page
		$response = new JsonResponse($feature);
		return $response;
	}

}
