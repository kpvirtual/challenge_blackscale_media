<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use MailSlurp\Configuration;
use MailSlurp\Apis\InboxControllerApi;
use MailSlurp\Apis\WaitForControllerApi;

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
        $this->getMailConfig = Configuration::getDefaultConfiguration()
                ->setApiKey('x-api-key', env('MAILSLURP_KEY'));
    }
    public function index()
    {
        echo(' Process Initialized........ <br>');
        // Make an initial request to the secure sit
        // Step 1 : Prepare the cookies and intiate the request to challenge page 
        // And reterive the stoekn from register page and ctoken from cookies
        $response =  $this->client->request('GET','https://challenge.blackscale.media/register.php');
        $html = $response->getBody();
        $formvalueArr =  $this->parseHtml($html);
        //print_r($formvalueArr );
        // Extract cookies from the response (if needed)
        echo(' Get the stoken from register page <br>');
        echo(' stoken =>'.$formvalueArr['stoken'].' <br>');
        // create email box
      
        echo(' Creating the temp email inbox <br>');
        $inboxController = new InboxControllerApi(null, $this->getMailConfig);
        
        $options = new \MailSlurp\Models\CreateInboxDto();
        $options->setName("blackscale media");
        $options->setPrefix("blackscale");
        $inbox = $inboxController->createInboxWithOptions($options);
        $inboxDetails = json_decode($inbox);
        $inboxid = $inboxDetails->id;
        $email = $inboxDetails->emailAddress;
        echo(' Email=>'.$email.'<br>');
        
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
        
        $formStep1 = [
            'fullname' => $uuid,
            'email' => $email,
            'stoken' => $formvalueArr['stoken'],
            'password' => '12345678',
            'email_signature' => base64_encode($email),
            ];
        
        // Send a POST request with form data and cookies
        echo(' Sending the request to verify.php endpoints <br>'.json_encode($formStep1).'<br>');
        // Step 2 : Pass the verification data to get the verification code on email
        $res =  $this->client->post('https://challenge.blackscale.media/verify.php', [
            'form_params' => $formStep1,
            'cookies' => $this->cookieJar,
            'headers' => $headers,         
        ]);
       
        $contents = $res->getBody()->getContents();
       
        if($res->getStatusCode()=="200"){
            //$getEmailVerificationCode = $this->verificationCode($uuid);
            $getEmailVerificationCode = $this->getVerificationCode($inboxid);
            echo(' Get the verification code : '.$getEmailVerificationCode.'<br>');
            //print_r($getEmailVerificationCode);
        }

        // Pre-Step 3 : Pass the verification code to invoke the captha code
        echo(' Sending the code to captcha.php endpoints <br>');
        $formStep2 = ['code' => $getEmailVerificationCode];
        $resCaptcha =  $this->client->post('https://challenge.blackscale.media/captcha.php', [
            'form_params' => $formStep2,
            'cookies' => $this->cookieJar,
            'headers' => $headers,         
        ]);
        $resCaptchaHtml = $resCaptcha->getBody()->getContents();
        //print(' Sending the code to captcha.php endpoints <br>'.$resCaptchaHtml.'<br>');
        // step3 : bypass the captcha
        preg_match('/class="g-recaptcha" data-sitekey="([^"]+)"/', $resCaptchaHtml, $matches);
        if($matches){
            $sitekey = $matches[1];
            echo(' Registration Successful <br>');
            echo(' Captcha site key is-'.$sitekey.'<br>');
        }

        //job execution formatting.
        $response = [
            'step1'=>$formStep1,
            'response_from_step1'=> $contents,
            'step2' => $formStep2,
            'response_from_step2' =>$resCaptchaHtml,
            'site_key' =>$sitekey,
        ];
        //return $this->sendResponse(  $response, 'Done successfully');

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
            $crawler = new Crawler($response->getBody()->getContents(),"https://www.mailinator.com/v4/public/inboxes.jsp?to=$emailBox");
            $filter = $crawler->filter('body > table');
            // gets back an array of values - in the "flat" array like above
            //wrapper-primary-table scrollbar
            return $filter;
        }
 
    
    private function getVerificationCode($inboxid)
        {
         echo(' Registration Successful');
         echo(' Fetching verification code');
    
            // GET latest email from MailSlurp inbox
            $wait_for_controller = new WaitForControllerApi(null, $this->getMailConfig);
            $timeout_ms = 90000;
            $unread_only = true;
            $email = $wait_for_controller->waitForLatestEmail(
                $inboxid,
                $timeout_ms,
                $unread_only
            );
    
            return str($email->getBody())->after(':')->trim()->__toString();
        }
}
