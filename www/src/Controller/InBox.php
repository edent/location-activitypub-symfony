<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpClient\HttpClient;

class InBox extends AbstractController
{
	#[Route("/inbox", name: "inbox")]
	public function inbox(): JsonResponse {

		//	Get the POST'ed data
		$request = Request::createFromGlobals();
		$inbox_message = $request->getPayload()->all();

		//	No type? Ignore it
		if ( !isset( $inbox_message["type"] ) ) { die(); }

		//	Get the type
		$inbox_type = $inbox_message["type"];

		//	Ignore deleted account notifications
		if ( "Delete" == $inbox_type ) { die(); }

		//	Log the request
		file_put_contents("logs/" . date("c") . " $inbox_type.json", print_r( json_encode($inbox_message), true ) ); 

		//	This inbox only responds to follow requests
		if ( "Follow" != $inbox_type ) { die(); }

		//	Get the parameters
		$inbox_id    = $inbox_message["id"];
		$inbox_actor = $inbox_message["actor"];
		$inbox_url   = parse_url($inbox_actor, PHP_URL_SCHEME) . "://" . parse_url($inbox_actor, PHP_URL_HOST);
		$inbox_host  = parse_url($inbox_actor, PHP_URL_HOST);

		//	Read existing users
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );
		//	Add user to list. Don't care about duplicate users, server is what's important
		$followers_json[$inbox_host]["users"][] = $inbox_actor;
		//	Save the new followers file
		file_put_contents( "followers.json", print_r( json_encode( $followers_json ), true ) );

		//	Response Message ID
		//	This isn't used for anything important so can just be a random number
		$guid = bin2hex(random_bytes(16));

		//	Create the Accept message
		$message = [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => "https://{$_SERVER['SERVER_NAME']}/{$guid}",
			'type'     => 'Accept',
			'actor'    => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			'object'   => [
				'@context' => "https://www.w3.org/ns/activitystreams",
				'id'       => $inbox_id,
				'type'     => $inbox_type,
				'actor'    => $inbox_actor,
				'object'   => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			]
		];
		$message_json = json_encode($message);

		//	The Accept is sent to the server of the user who requested the follow
		//	TODO: The path doesn't *always* end with/inbox
		$host = $inbox_host;
		$path = parse_url($inbox_actor, PHP_URL_PATH) . "/inbox";
		
		//	Set up signing
		$privateKey = $_ENV["PRIVATE_KEY"];
		$keyId = "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}#main-key";

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
	
		// Specify the URL of the remote server's inbox
		//	TODO: The path doesn't *always* end with/inbox
		$remoteServerUrl = $inbox_actor . "/inbox";

		//	POST the message and header to the requester's inbox
		$ch = curl_init($remoteServerUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	
		$response = curl_exec($ch);
		if(curl_errno($ch)) {
			file_put_contents("error.txt",  curl_error($ch) );
		}
		curl_close($ch);

		//	Render the page
		//	Not necessary - but gives us something to look at if doing this manually!
		$response = new JsonResponse($message);	
		$response->headers->add($headers);
		return $response;
	}
}