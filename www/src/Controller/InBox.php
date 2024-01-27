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

		$header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';

		$headers = array(
			'host'         => $host,
			'date'         => $date,
			'signature'    => $header,
			'digest'       => 'SHA-256=' . $digest,
			'content-type' => 'application/activity+json',
			'accept'       => 'application/activity+json',
		);
	
		file_put_contents("follow.txt",print_r($message, true));
		file_put_contents("headers.txt",print_r($headers, true));

		// Specify the URL of the remote server
		$remoteServerUrl = $inbox_actor . "/inbox";

		file_put_contents("remote.txt", $remoteServerUrl);

		$ch = curl_init($remoteServerUrl);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		file_put_contents("ch.txt",print_r($ch, true));

		$response = curl_exec($ch);
		if(curl_errno($ch)) {
			file_put_contents("error.txt",  curl_error($ch) );
		} else {
			file_put_contents("curl.txt", $response);
		}
		file_put_contents("ch1.txt",print_r($ch, true));
		curl_close($ch);
		file_put_contents("ch2.txt",print_r($ch, true));


		// //	Send the response
		// $client = HttpClient::create();
		// file_put_contents("client.txt", serialize($client));

		// // Send the POST request
		// $send = $client->request('POST', $remoteServerUrl, [
		// 	'headers' => $headers,
		// 	'json' => $message, // Use 'json' option to automatically encode data as JSON
		// ]);

		// // Get the response content
		// file_put_contents("send.txt",serialize($send->toArray()));

		// $content = $send->getContent();
		// file_put_contents("content.txt",serialize($content));

		//	Render the page
		$response = new JsonResponse($message);	
		$response->headers->add($headers);
		return $response;
	}

	#[Route("/test", name: "test")]
	public function test() {
		//	Send the response
		// Create an instance of the HttpClient
		$client = HttpClient::create();

		// Specify the URL of the remote server
		$remoteServerUrl = "https://example.com/inbox";

		$headers = [
			'Content-Type' => 'application/activity+json',
			'Accept'       => 'application/activity+json',
		];

		$message = [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://location.edent.tel/',
			'type'     => 'Accept',
		];

		// Send the POST request
		$response = $client->request('POST', $remoteServerUrl, [
			'headers' => $headers,
			'json' => $message, // Use 'json' option to automatically encode data as JSON
		]);

		var_dump($response);

		// Get the response content
		$content = $response->getContent();
		file_put_contents("content.txt",serialize($content));
	}
}
