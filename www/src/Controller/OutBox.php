<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\HttpClient\HttpClient;

class OutBox extends AbstractController
{
	#[Route("/outbox", name: "outbox")]
	public function outbox(): JsonResponse {

		//	Get all posts
		$posts = array_reverse( glob("posts/" . "*.json") );
		//	Number of posts
		$totalItems = count( $posts );
		//	Create an ordered list
		$orderedItems = [];
		foreach ($posts as $post) {
			$orderedItems[] = array(
				"type"   => "Create",
				"actor"  => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
				"object" => "https://{$_SERVER['SERVER_NAME']}/{$post}"
			);
		}

		//	Create User's outbox
		$feature = array(
			"@context"     => "https://www.w3.org/ns/activitystreams",
			"id"           => "https://{$_SERVER['SERVER_NAME']}/outbox",
			"type"         => "OrderedCollection",
			"totalItems"   =>  $totalItems,
			"summary"      => "All the location posts",
			"orderedItems" =>  $orderedItems
		);

		//	Render the page
		$response = new JsonResponse($feature);	
		return $response;
	}

	#[Route("/send", name: "send")]
	public function send( Request $request ): RedirectResponse {

		//	Was this POST'ed?
		if ( $request->isMethod('POST') ) {
			//	Password check
			if ( $_ENV["API_PASSWORD"] != $request->request->get( "password" ) ) {
				die();
			}

			//	Get the POST'ed data
			$PlaceName = $request->request->get( "PlaceName" );
			$PlaceLat  = $request->request->get( "PlaceLat"  );
			$PlaceLon  = $request->request->get( "PlaceLon"  );
			$PlaceType = $request->request->get( "PlaceType" );
			$PlaceID   = $request->request->get( "PlaceID"   );
			$details   = $request->request->get( "details"   );
			$alt       = $request->request->get( "alt"       );

			//	Get any hashtags
			$hashtags = [];
			preg_match_all('/#(\w+)/', $details, $matches);
			foreach ($matches[1] as $match) {
				$hashtags[] = $match;
			}

			//	Construct the tag value for the post
			$tags = [];
			foreach ( $hashtags as $hashtag ) {
				$tags[] = array(
					"type" => "Hashtag",
					"name" => "#{$hashtag}",
				);
			}

			//	Add links for hashtags into the text
			$details = preg_replace('/(?<!\S)#([0-9\p{L}]+)/u', "<a href='https://{$_SERVER['SERVER_NAME']}/tag/$1'>#$1</a>", $details);

			//	Construct the content
			$content = "<p>🌐 Checked-in to: <a href='https://www.openstreetmap.org/{$PlaceType}/{$PlaceID}'>{$PlaceName}</a><br>{$details}</p>";

		} else {
			die();
		}

		//	Is there an image?
		if ( isset( $_FILES['photo']['tmp_name'] ) && ("" != $_FILES['photo']['tmp_name'] ) ) {
			$photo = $_FILES['photo']['tmp_name'];

			//	Files are stored according to their hash
			//	A hash of "abc123" is stored in "/a/b/abc123.jpg"
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

			//	Construct the attachment value for the post
			$attachment = [
				"type"      => "Image",
				"mediaType" => "image/jpeg",
				"url"       => "https://{$_SERVER['SERVER_NAME']}/{$photo_full_path}",
				"name"      => $alt
		  ];

		} else {
			$attachment = [];
		}

		//	Current time
		$timestamp = date("c");

		//	Outgoing Message ID
		$guid = $this->uuid();

		//	Construct the Note
		$note = [
			"@context"     => array(
				"https://www.w3.org/ns/activitystreams",
				["Hashtag" => "https://www.w3.org/ns/activitystreams#Hashtag"]
			),
			"id"           => "https://{$_SERVER['SERVER_NAME']}/posts/{$guid}.json",
			"type"         => "Note",
			"published"    => $timestamp,
			"attributedTo" => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			"content"      => $content,
			"contentMap"   => ["en" => $content],
			"to"           => ["https://www.w3.org/ns/activitystreams#Public"],
			"attachment"   => $attachment,
			"location"     => [
				"name"         => $PlaceName,
				"type"         => "Place",
				"longitude"    => $PlaceLon,
				"latitude"     => $PlaceLat
			],
			"tag"          => $tags
		];

		//	Construct the Message
		$message = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id"       => "https://{$_SERVER['SERVER_NAME']}/posts/{$guid}.json",
			"type"     => "Create",
			"actor"    => "https://{$_SERVER['SERVER_NAME']}/{$_ENV['USERNAME']}",
			"to"       => [
				"https://www.w3.org/ns/activitystreams#Public"
			],
			"cc"       => [
				"https://{$_SERVER['SERVER_NAME']}/followers"
			],
			"object"   => $note
		];
		$message_json = json_encode($message);

		//	Create the context for the permalink
		$note = [ "@context" => "https://www.w3.org/ns/activitystreams", ...$note];
		
		//	Save the permalink
		$note_json = json_encode($note);
		file_put_contents( "posts/{$guid}.json", print_r($note_json, true) );

		//	Read existing users and get their hosts
		$followers_file = file_get_contents( "followers.json" );
		$followers_json = json_decode( $followers_file, true );		
		$hosts = array_keys($followers_json);

		//	Prepare to use the multiple cURL handle
		$mh = curl_multi_init();

		//	Loop through all the severs of the followers
		//	Each server needs its own cURL handle
		//	Each POST to an inbox needs to be signed separately
		foreach ($hosts as $host) {
			$path = '/inbox';
			
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
			
			// Specify the URL of the remote server
			$remoteServerUrl = "https://{$host}{$path}";
		
			//	POST the message and header to the requester's inbox
			$ch = curl_init( $remoteServerUrl );
		
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $message_json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			//	Add the handle to the multi-handle
			curl_multi_add_handle( $mh, $ch );
		}

		//	Execute the multi-handle
		do {
			$status = curl_multi_exec( $mh, $active );
			if ( $active ) {
				curl_multi_select( $mh );
			}
		} while ( $active && $status == CURLM_OK );

		//	Close the multi-handle
		curl_multi_close( $mh );

		//	Render the JSON so the user can see the POST has worked
		return $this->redirect("https://{$_SERVER['SERVER_NAME']}/posts/{$guid}.json");
	}

	public function uuid() {
		//	Date sortable UUID
		return sprintf('%08x-%04x-%04x-%04x-%012x',
			time(),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffffffffffff)
		);
	}
}
