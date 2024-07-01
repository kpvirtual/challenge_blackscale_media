<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class AutobotController extends BaseController
{
    //
    function __construct() {
        // Create a CookieJar instance to manage cookies
        $this->cookieJar = new \GuzzleHttp\Cookie\CookieJar;
        $this->client = new \GuzzleHttp\Client(['cookies' =>  true]);
        $this->clientCrawler = new \GuzzleHttp\Client();
        $this->client->request('GET', 'https://challenge.blackscale.media');
        $this->cookieJar = $this->client->getConfig('cookies');
    }
    public function index()
    {
        // Make an initial request to the secure sit
        // Step 1 : Prepare the cookies and intiate the request to challenge page 
        // And reterive the stoekn from register page and ctoken from cookies
        $response =  $this->client->request('GET','https://challenge.blackscale.media/register.php');
        $html = $response->getBody();
        $formvalueArr =  $this->parseHtml($html);
        //print_r($formvalueArr );
        // Extract cookies from the response (if needed)
       
        // Prepare data for the POST request (login or form submission)
        $headers = [
            'Accept' => 'text/html',
            "User-Agent"=>
      "Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.64 Safari/537.11",
            'Referer' => 'https://challenge.blackscale.media/register.php',
            'Host' => 'challenge.blackscale.media',
            "Connection"=> "keep-alive",
            ];

        $uuid =  (string) Str::uuid();
        
        $formData = [
            'fullname' => $uuid,
            'email' => "$uuid@mailinator.com",
            'stoken' => $formvalueArr['stoken'],
            'password' => '12345678',
            'email_signature' => base64_encode("$uuid@mailinator.com"),
            ];
        
        // Send a POST request with form data and cookies
     
        // Step 2 : Pass the verification data to get the verification code on email
        $res =  $this->client->post('https://challenge.blackscale.media/verify.php', [
            'form_params' => $formData,
            'cookies' => $this->cookieJar,
            'headers' => $headers,         
        ]);
       
        echo ( $contents = $res->getBody()->getContents() );

        if($res->getStatusCode()=="200")
            $getEmailVerificationCode = $this->verificationCode($uuid);
        //print_r($getEmailVerificationCode);


        // Step 3 : Pass the verification code to invoke the captha code
        $formData = ['code' => $getEmailVerificationCode];
        $resCaptcha =  $this->client->post('https://challenge.blackscale.media/captcha.php', [
            'form_params' => $formData,
            'cookies' => $this->cookieJar,
            'headers' => $headers,         
        ]);
        echo $resCaptcha->getBody()->getContents();
        return $this->sendResponse(  $formData, 'Code sent successfully.');

    }
    function parseHtml($html)
        {

            // Using Symfony DOM Crawler
            $crawler = new Crawler($html,"https://challenge.blackscale.media/register.php");
            $form = $crawler->selectButton('Register')->form();
            // gets back an array of values - in the "flat" array like above

            return $form->getValues();
        }

    function verificationCode($emailBox)
        {
            // Using Symfony DOM Crawler
            $response = $this->clientCrawler->request('GET', "https://www.mailinator.com/v4/public/inboxes.jsp?to=$emailBox");
           print_r($response->getBody()->getContents());
            $crawler = new Crawler($response->getBody()->getContents(),"https://www.mailinator.com/v4/public/inboxes.jsp?to=$emailBox");
            $filter = $crawler->filter('body > table');
            // gets back an array of values - in the "flat" array like above
            //wrapper-primary-table scrollbar
            return $filter;
        }
}
