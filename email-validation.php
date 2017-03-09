<?php
//return;
/*
Plugin Name:  Email Validation
Plugin URI: https://wplikeapro.com/email-validation
Description: Add email validation to mailpoet signup forms, to prevent sending any emails to bogus emails. This will completely prevent them from signing up so not even the confirmation email will be sent (if double-optin is enabled).
Author: Chris Nesbit
Author URI: https://wplikeapro.com
Version: 0.1
License: GPLv2 or later
*/

// https://github.com/zytzagoo/smtp-validate-email/blob/master/smtp-validate-email.php
// https://github.com/Roly67/php-email-validation/blob/master/src/emailverify.php

class WPLIKEAPRO_Email_Validation extends WP_REST_Controller {
  
  /*
  $TCP_BUFFER_SIZE defines the in memory buffer size used for SMTP conversations.
  Default of 1024 is fine in most cases.
  */
  protected $TCP_BUFFER_SIZE;
  protected $HTTP_HOST;
  
  public function __construct() {
  	//ini_set('display_errors', 1);
		//ini_set('display_startup_errors', 1);
		//error_reporting(E_ALL);
		
    $this->TCP_BUFFER_SIZE = 1024;
    $this->HTTP_HOST = $_SERVER["HTTP_HOST"];
    $this->version   = '1';
    $this->namespace = 'wplikeapro/v' . $this->version;
    $this->rest_base = 'emailvalidation';
  }

  /**
   * Register the routes for the objects of the controller.
   */
  public function register_routes() {
    register_rest_route($this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array( $this, 'validate_email' ),
        'permission_callback' => array( $this, 'validate_email_permissions_check' ),
      ),
    ));
  }
 
  /**
   * Check user is logged in
   *
   * @return bool
   */
  public function validate_email_permissions_check( $request ) {
		return true; //we don't care if the user is logged in
  }

  /**
   * Insert image into media library
   *
   * @param WP_REST_Request  $request  Full data about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function validate_email( $request ) {
    $message = null;
    
		$email = $request->get_param( 'email' );
    $isvalid = filter_var($email, FILTER_VALIDATE_EMAIL) != false;
    $domain = $isvalid ? $this->get_domain($email) : null;
  	
    if ($isvalid) {
    	$isvalid = $this->domain_exists($domain);
    }
    
    if ($isvalid) {
      $mxHost = $this->get_mx_for_domain($domain);
  		if ($mxHost) {
    	  $status = $this->mailbox_exists($domain, $mxHost, $email, 'admin@wplikeapro.com');
    	  print $status;
    	  if (!is_bool($status)) {
    	    //status unknown. return true, with qualifying message
  		    $isvalid = true;
          $message = $status;
    	    
    	  }
  		} else {
  		  $isvalid = false;
  		} 
    }
		
    
    $response = rest_ensure_response( 
      array( 
        'isvalid' => $isvalid, 
        'message' => $message, 
        'email' => $email
      )
    );
    
    return $response;
  }
  
  protected function get_domain($email) {
		list($user, $domain) = explode('@', $email);
		return $domain;
  }
  
  protected function domain_exists($domain, $record = 'MX') {
		return checkdnsrr($domain, $record);
	}
	
  protected function get_mx_for_domain($domain, $record = 'MX') {
		if (getmxrr($domain, $mxHost)) {
		  return $mxHost;
		}
		
		return null;
	}
	
	protected function mailbox_exists($domain, $mxHost, $email, $fromEmail) {
  	$timeout = 3;
  	
  	// Prep up the function return.
  	$Return = array();  
  	
		// Get the IP address of first MX record
		$connectAddress = $mxHost[0]; 
		// Open TCP connection to IP address on port 25 (default SMTP port)
		if (!$connect = fsockopen ( $connectAddress, 25, $errNo, $errMessage, $timeout)) {
    	return $errMessage;
		}
		
		// Rerun array element index 1 contains the IP address of the target mail server
		$Return[1] = $connectAddress;
		  
		// look for a response code of 220 using Regex
		if ( preg_match ( "/^220/", $reply = fgets ( $connect, $this->TCP_BUFFER_SIZE ) ) ) { 
			
			// Start SMTP conversation with HELO
			fputs ( $connect, "HELO ". $this->HTTP_HOST ."\r\n" ); 
			$reply = fgets ( $connect, $this->TCP_BUFFER_SIZE );
			// Next, do MAIL FROM:
			fputs ( $connect, "MAIL FROM: <". $fromEmail .">\r\n" ); 
			$reply = fgets ( $connect, $this->TCP_BUFFER_SIZE );

			// Next, do RCPT TO:
			fputs ( $connect, "RCPT TO: <{$email}>\r\n" ); 
			$to_reply = fgets ( $connect, $this->TCP_BUFFER_SIZE );

			// Quit the SMTP conversation.
			fputs ( $connect, "QUIT\r\n"); 
			// Close TCP connection
			fclose($connect); 
		} 
    
		if ( preg_match ( "/^250/", $to_reply ) ) { 
			return true; 
		}
		if ( preg_match ( "/^550/", $to_reply ) ) {
			return false; 
		}
		
		return $to_reply;
	}
}
 
function init_wplikeapro_email_validation() {
  $controller = new WPLIKEAPRO_Email_Validation();
  $controller->register_routes();
}
 
add_action( 'rest_api_init', 'init_wplikeapro_email_validation' );
 
?>