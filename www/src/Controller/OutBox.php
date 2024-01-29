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
	public function send( Request $request ) {

		//	Was this POST'd?
		if ( $request->isMethod('POST') ) {
			//	Password check
			if ( $_ENV["PASSWORD"] != $request->request->get( "password" ) ) {
				die();
			}

			$PlaceName = $request->request->get( "PlaceName" );
			$PlaceLat  = $request->request->get( "PlaceLat"  );
			$PlaceLon  = $request->request->get( "PlaceLon"  );
			$PlaceType = $request->request->get( "PlaceType" );
			$PlaceID   = $request->request->get( "PlaceID"   );
			$details   = $request->request->get( "details"   );
			$alt       = $request->request->get( "alt"       );

			$content = "<p>Checked-in to: <a href='https://www.openstreetmap.org/{$PlaceType}/{$PlaceID}'>{$PlaceName}</a><br>{$details}</p>";

		} else {
			$response = new Response(
				"Nope!",
				Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
				["content-type" => "text/html"]
			);
			die();
		}

		//	Is there an image?
		if ( isset( $_FILES['photo']['tmp_name'] ) ) {
			$photo = $_FILES['photo']['tmp_name'];

			//	Files are stored according to their hash
			//	So "abc123" is stored as "/a/b/abc123.jpg"
			$sha1 = sha1_file( $photo );
			$directory = substr( $sha1, 0, 1);
			$subdirectory = substr( $sha1, 1, 1);
			$photo_path = "images/" . $directory . "/" . $subdirectory . "/";
			$photo_full_path = $photo_path . $sha1 . ".jpg";

			//	Move media to the correct location
			//	Create a directory if it doesn't exist
			if ( !is_dir( $photo_path ) ) {
				mkdir( $photo_path, 0777, true );
			}
			move_uploaded_file($photo, $photo_full_path);

			$attachment = [
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://location.edent.tel/{$photo_full_path}",
				"name"      => $alt
		  ];

		} else {
			$attachment = [];
		}

		$timestamp = date("c");
		//	Outgoing Message ID
		$guid = bin2hex(random_bytes(16));

		$note = [
			"id"           => "https://location.edent.tel/{$guid}",
			"type"         => "Note",
			"published"    => $timestamp,
			"attributedTo" => "https://location.edent.tel/edent_location",
			"content"      => $content,
			"to"           => [
				"https://www.w3.org/ns/activitystreams#Public"
			],
			"attachment"   => $attachment,
			"location"    => [
				"name"      => $PlaceName,
				"type"      => "Place",
				"longitude" => $PlaceLon,
				"latitude"  => $PlaceLat
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
	
			// $curl_error_log = fopen(dirname(__FILE__).'/outcurlerr.txt', 'w');
	
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			// curl_setopt($ch, CURLOPT_VERBOSE, 1);
			// curl_setopt($ch, CURLOPT_STDERR, $curl_error_log);
		
			$response = curl_exec($ch);
			if(curl_errno($ch)) {
				file_put_contents("outerror.txt",  curl_error($ch) );
			} else {
				// file_put_contents("outcurl.txt", $response);
			}
			curl_close($ch);
		}

		$response = new Response(
			"https://location.edent.tel/{$guid}",
			Response::HTTP_OK,
			["content-type" => "text/plain"]
		);
		return $response;
	}
}
