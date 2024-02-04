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
			"id"        => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			"type"      => "Person",
			"following" => "https://{$_SERVER['SERVER_NAME']}/following",
			"followers" => "https://{$_SERVER['SERVER_NAME']}/followers",
			"inbox"     => "https://{$_SERVER['SERVER_NAME']}/inbox",
			"outbox"    => "https://{$_SERVER['SERVER_NAME']}/outbox",
			"preferredUsername" => "{$_ENV['USERNAME']}",
			"name"      => "Terence Eden's location",
			"summary"   => "Where @edent is. All replies are ignored. This is a write-only account.",
			"url"       => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			"manuallyApprovesFollowers" => true,
			"discoverable" => true,
			"indexable"    => true,
			"published" => "2000-01-01T00:00:00Z",
			"icon" => array(
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://{$_SERVER['SERVER_NAME']}/icon.jpg"
			),
			"image" => array(
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://{$_SERVER['SERVER_NAME']}/image.jpg"
			),
			"publicKey" => array(
				"id"           => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}#main-key",
				"owner"        => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
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
			"id"         => "https://{$_SERVER['SERVER_NAME']}/following",
			"type"       => "Collection",
			"totalItems" => 0,
			"items"      => []
		);
		//	Render the page
		$response = new JsonResponse($feature);
		return $response;
	}

	#[Route("/followers", name: "followers")]
	public function followers(): JsonResponse {

		//	Read existing followers
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );		

		$followers = [];
		foreach ($followers_json as $domain => $users_array) {
			foreach ($users_array as $users) {
				foreach ($users as $user ) {
					$followers[] = $user ;
				}
			}
		}
		//	Remove any duplicates
		$followers = array_unique( $followers );

		//	Construct the collection
		$followers_collection = [];
		foreach ($followers as $follower) {
			$followers_collection[] = ["type" => "Person", "id" => $follower];
		}

		//	Create User's Profile
		$feature = array(
				"@context"   => "https://www.w3.org/ns/activitystreams",
				"id"         => "https://{$_SERVER['SERVER_NAME']}/followers",
				"type"       => "Collection",
				"totalItems" => count( $followers_collection ),
				"items"      => $followers_collection
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

		//	Loop through the posts
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
}
