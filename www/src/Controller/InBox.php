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
		if ( !isset( $inbox_message["type"] ) ) { file_put_contents("new.txt",serialize($inbox_message)); die(); }
		$inbox_type = $inbox_message["type"];
		if ( "Follow" != $inbox_type ) { file_put_contents("notfollow.txt",serialize($inbox_message)); die(); }
		$inbox_id = $inbox_message["id"];
		$inbox_actor = $inbox_message["actor"];
		$inbox_url = parse_url($inbox_actor, PHP_URL_SCHEME) . "://" . parse_url($inbox_actor, PHP_URL_HOST);
                $inbox_host = parse_url($inbox_actor, PHP_URL_HOST);

		//	Response Message ID
		$guid = bin2hex(random_bytes(16));

		//	Accept message
		$message = [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://location.edent.tel/' . $guid,
			'type'     => 'Accept',
			'actor'    => 'https://location.edent.tel/edent_location',
			'object'   => [
				'@context' => 'https://www.w3.org/ns/activitystreams',
				'id'       => $inbox_id,
				'type'     => $inbox_type,
				'actor'    => $inbox_actor,
				'object'   => 'https://location.edent.tel/edent_location',
			]
		];

		$host = $inbox_host;
		$path = '/inbox';
		$privateKey = $_ENV["PRIVATE_KEY"];
		$keyId = 'https://location.edent.tel/edent_location';

		$hash = hash('sha256', json_encode($message), true);
		$digest = base64_encode($hash);

		$date = date('D, d M Y H:i:s \G\M\T');
		$signer = openssl_get_privatekey($privateKey);
		$stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
		openssl_sign($stringToSign, $signature, $signer, OPENSSL_ALGO_SHA256);
		$signature_b64 = base64_encode($signature);

		$header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . 
'"';

		$headers = [
			'Host'         => $host,
    			'Date'         => $date,
			'Signature'    => $header,
			'Digest'       => 'SHA-256=' . $digest,
			'Content-Type' => 'application/activity+json',
			'Accept'       => 'application/activity+json',
		];
	
		file_put_contents("follow.txt",serialize($message));
                file_put_contents("headers.txt",serialize($headers));

		//	Send the response
		// Create an instance of the HttpClient
		$client = HttpClient::create();

		// Specify the URL of the remote server
		$remoteServerUrl = $inbox_actor . "/inbox";

		// Send the POST request
		$response = $client->request('POST', $remoteServerUrl, [
			'headers' => $headers,
			'json' => $message, // Use 'json' option to automatically encode data as JSON
		]);

		// Get the response content
		$content = $response->getContent();
		file_put_contents("content.txt",serialize($content));

		//	Render the page
		$response = new JsonResponse($message);	
		$response->headers->add($headers);
		return $response;
	}
}
