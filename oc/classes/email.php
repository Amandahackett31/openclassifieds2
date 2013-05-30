<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Simple email class
 *
 * @package    OC
 * @category   Core
 * @author     Chema <chema@garridodiaz.com>, Slobodan <slobodan.josifovic@gmail.com>
 * @copyright  (c) 2009-2013 Open Classifieds Team
 * @license    GPL v3
 */


class Email {

    /**
     * sends an email using our configs
     * @param  string/array $to       array(array('name'=>'chema','email'=>'chema@'),)
     * @param  [type] $to_name   [description]
     * @param  [type] $subject   [description]
     * @param  [type] $body      [description]
     * @param  [type] $reply     [description]
     * @param  [type] $replyName [description]
     * @param  [type] $file      [description]
     * @return boolean
     */
    public static function send($to,$to_name='',$subject,$body,$reply,$replyName,$file = NULL)
    {
        require_once Kohana::find_file('vendor', 'php-mailer/phpmailer','php');

        //get the template from the html email boilerplate
        $body = View::factory('email',array('title'=>$subject,'content'=>nl2br($body)))->render();

        $mail= new PHPMailer();

        if(core::config('email.smtp_active') == TRUE)
        { 
            //SMTP HOST config
            if (core::config('email.smtp_host')!="")
            {
                $mail->IsSMTP();
                $mail->Host       = core::config('email.smtp_host');              // sets custom SMTP server
            }

            //SMTP PORT config
            if (core::config('email.smtp_port')!="")
            {
                $mail->Port       = core::config('email.smtp_port');              // set a custom SMTP port
            }

            //SMTP AUTH config

            if (core::config('email.smtp_auth') == TRUE)
            {
                $mail->SMTPAuth   = TRUE;                                                  // enable SMTP authentication
                $mail->Username   = core::config('email.smtp_user');              // SMTP username
                $mail->Password   = core::config('email.smtp_pass');              // SMTP password
               

                if (core::config('email.smtp_ssl') == TRUE)
                {
                    $mail->SMTPSecure = "ssl";                  // sets the prefix to the server
                }
                    
            }

            $mail->From       = core::config('email.notify_email');
            $mail->FromName   = "no-reply ".core::config('general.site_name');
            $mail->Subject    = $subject;
            $mail->MsgHTML($body);

            if($file !== NULL) $mail->AddAttachment($file['tmp_name'],$file['name']);

            $mail->AddReplyTo($reply,$replyName);//they answer here

            if (is_array($to))
            {
                foreach ($to as $contact) 
                    $mail->AddBCC($contact['email'],$contact['name']);               
            }
            else
                $mail->AddAddress($to,$to_name);


            $mail->IsHTML(TRUE); // send as HTML

            if(!$mail->Send()) 
            {//to see if we return a message or a value bolean
                Alert::set(Alert::ALERT,"Mailer Error: " . $mail->ErrorInfo);
                return FALSE;
            } 
            else 
                return TRUE;
        }    
        else
        {
            $headers = 'MIME-Version: 1.0' . PHP_EOL;
            $headers.= 'Content-type: text/html; charset=utf8'. PHP_EOL;
            $headers.= 'From: '.$reply.PHP_EOL;
            $headers.= 'Reply-To: '.$reply.PHP_EOL;
            $headers.= 'Return-Path: '.$reply.PHP_EOL;
            $headers.= 'X-Mailer: PHP/' . phpversion().PHP_EOL;

            if (is_array($to))
            {
                $headers.='Bcc: '; 
                foreach ($to as $contact) 
                {
                    $headers.= $contact['name'].' <'.$contact['email'].'>, '; 
                }
                $headers.='\r\n'; 
                $to = '';
            }
            else 
                $to = $to_name.' <'.$to.'>';

            return mail($to,$subject,$body,$headers);
        }
        // Sent at 9:39 AM on Friday
 
    }


    /**
     * sends an email using content from model_content
     * @param  string $to        
     * @param  string $to_name   
     * @param  string $from      
     * @param  string $from_name 
     * @param  string $content   seotitle from Model_Content
     * @param  array $replace   key value to replace at subject and body
     * @return boolean            s
     */
    public static function content($to,$to_name='',$from = NULL, $from_name =NULL ,$content,$replace)
    {
        
        $email = Model_Content::get($content,'email');

        //content found
        if ($email->loaded())
        { 
            if ($replace===NULL) 
                $replace = array();

            if ($from === NULL)
                $from = $email->from_email;

            if ($from_name === NULL )
                $from_name = core::config('general.site_name');

            //adding extra replaces
            $replace+= array('[SITE.NAME]'      =>  core::config('general.site_name'),
                             '[SITE.URL]'       =>  core::config('general.base_url'),
                             '[USER.NAME]'      =>  $to,
                             '[USER.EMAIL]'     =>  $to_name);

            $subject = str_replace(array_keys($replace), array_values($replace), $email->title);
            $body    = str_replace(array_keys($replace), array_values($replace), $email->description);

            return Email::send($to,$to_name,$subject,$body,$from,$from_name); 

        }
        else return FALSE;

    }


} //en email