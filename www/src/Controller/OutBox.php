<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpClient\HttpClient;

class OutBox extends AbstractController
{
	#[Route("/send", name: "send")]
	public function send(): JsonResponse {

		$timestamp = date("c");
		//	Outgoing Message ID
		$guid = bin2hex(random_bytes(16));

		$note = [
			"id"           => "https://location.edent.tel/{$guid}",
			"type"         => "Note",
			"published"    => $timestamp,
			"attributedTo" => "https://location.edent.tel/edent_location",
			"content"      => "Another test <a href=\"https://www.openstreetmap.org/way/958999496\">John Lennon's Imagine Mosaic</a>.",
			"to"           => [
				"https://www.w3.org/ns/activitystreams#Public"
			],
			"attachment"   => [
				  "type"      => "Image",
				  "mediaType" => "image/jpeg",
				  "url"       => "https://fastly.4sqi.net/img/general/590x786/56367_9pxuZJD7d1hgPdaMFcFq1pipvTTMynBJsYcpHH-b8mU.jpg",
				  "name"      => "A photo of a mosaic which says 'Imagine'."
			 ],
			 "location"    => [
				"name"      => "John Lennon's Imagine",
				"type"      => "Place",
				"longitude" => 40.77563,
				"latitude"  => -73.97474
			]
		];

		//	Message
		$message = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id"       => "https://location.edent.tel/{$guid}",
			"type"     => "Create",
			"actor"    => "https://location.edent.tel/edent_location",
			"to"       => [
				"https://www.w3.org/ns/activitystreams#Public"
			],
			"cc"       => [
				"https://location.edent.tel/followers"
			],
			"object"   => $note
		];
		$message_json = json_encode($message);

		//	Save the permalink
		$note = [ "@context" => "https://www.w3.org/ns/activitystreams", ...$note];
		$note_json = json_encode($note);
		file_put_contents($guid,print_r($note_json, true));

		//	Read existing users and get their hosts
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );		
		$hosts = array_keys($followers_json);

		//	Loop through all the severs of the followers
		foreach ($hosts as $host) {
			$path = '/inbox';
			
			//	Set up signing
			$privateKey = $_ENV["PRIVATE_KEY"];
			$keyId = 'https://location.edent.tel/edent_location#main-key';
	
			$hash = hash('sha256', $message_json, true);
			$digest = base64_encode($hash);
			$date = date('D, d M Y H:i:s \G\M\T');
	
			$signer = openssl_get_privatekey($privateKey);
			$stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
			openssl_sign($stringToSign, $signature, $signer, OPENSSL_ALGO_SHA256);
			$signature_b64 = base64_encode($signature);
	
			$header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';
	
			//	Header for POST reply
			$headers = array(
						  "Host: {$host}",
						  "Date: {$date}",
						"Digest: SHA-256={$digest}",
					"Signature: {$header}",
				"Content-Type: application/activity+json",
						"Accept: application/activity+json",
			);
			
			// Specify the URL of the remote server
			$remoteServerUrl = "https://{$host}{$path}";
		
			//	POST the message and header to the requester's inbox
			$ch = curl_init($remoteServerUrl);
	
			$curl_error_log = fopen(dirname(__FILE__).'/outcurlerr.txt', 'w');
	
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_STDERR, $curl_error_log);
		
			$response = curl_exec($ch);
			if(curl_errno($ch)) {
				file_put_contents("outerror.txt",  curl_error($ch) );
			} else {
				file_put_contents("outcurl.txt", $response);
			}
			curl_close($ch);
		}


		//	Render the page
		//	Not necessary - but gives us something to look at!
		$response = new JsonResponse($message);	
		$response->headers->add($headers);
		return $response;
	}
}
