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
			"summary"   => "Where @edent is. All replies are ignored. This is a write-only account.",
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

		//	Read existing followers and count them
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );		
		$followers_total = 0;
		foreach ($followers_json as $domain => $users) {
			$followers_total += count($users['users']);
		}

		//	Create User's Profile
		$feature = array(
				"@context"   => "https://www.w3.org/ns/activitystreams",
				"id"         => "https://location.edent.tel/followers",
				"type"       => "OrderedCollection",
				"totalItems" => $followers_total,
				"first"      => "https://location.edent.tel/follower_accts"
		);
		
		//	Render the page
		$response = new JsonResponse($feature);
		return $response;
	}

	#[Route("/geojson", name: "geojson")]
	public function geojson(): JsonResponse {
		//	Get all posts
		$posts = glob("posts/" . "*.json");
		
		//	Create an ordered list
		$features = [];

		//	Loop through them all
		foreach ($posts as $post) {
			//	Get contents of the file
			$feature = json_decode( file_get_contents( $post ) );

			//	Build the feature
			$features[] = array(
				"id"       => $feature->id,
				"type"     => "Feature",
				"geometry" => [
					"type"        => "Point",
					"coordinates" => [
						floatval( $feature->location->longitude ),
						floatval( $feature->location->latitude  )
					]
				],
				"properties" => [
					"created_at"   => $feature->published,
					"popupContent" => $feature->content,
					"media"        => [
						array( 
							"url" => isset( $feature->attachment->url ) ? $feature->attachment->url : ""	
						)
					]
				]
			);
		}

		//	Construct the GeoJSON
		$geojson = array(
			"type"     => "FeatureCollection",
			"features" => $features
		);

		//	Render the page
		$response = new JsonResponse($geojson);
		return $response;
	}

	#[Route('/', name: 'base')]
	public function base(): Response {
		return $this->render('index.html.twig');
	}

}
