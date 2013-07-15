<?php
/**
 * A password-less login system.
 *
 * @package PasswordLessLogin
 * @author StathisG (Stathis Goudoulakis, me@stathisg.com)
 * @license New BSD
 * @version 1.0
 */

class PasswordLessLogin
{
    /* Configuration Variables - Read comments for details */

    private $pdo;

    /* Database related configuration */
    private $db_host = ''; //Database hostname (usually 'localhost')
    private $db_username = ''; //Database username
    private $db_password = ''; //Database password
    private $db_name = ''; //Database name
    private $db_table_name = 'password_less_login'; //Database table name (must be the same as the one used in create_table.sql)

    /* Path to PHPMailer, including a trailing slash (e.g. 'phpmailer/' if the files are located in the phpmailer folder);
    PasswordLessLogin needs 'class.phpmailer.php' and 'class.smtp.php' files (download from https://github.com/Synchro/PHPMailer);
    In case you want to use a solution other than PHPMailer, you will have to modify the 'send_login_email' function */
    private $phpmailer_folder = '';

    /* SMTP email server related configuration */
    private $email_host = ''; //SMTP server
    private $email_port = 0; //SMTP port (leave zero for default)
    private $email_username = ''; //SMTP username
    private $email_password = ''; //SMTP password
    private $email_from_email = ''; //Email address from which the login email will be sent
    private $email_from_name = ''; //Name from which the login email will be sent

    /* The subject of the login email */
    private $email_subject = '';

    /* The base URL for the login page (e.g. http://example.com/login.php?code=); the generated code will be attached at the end automatically */
    private $login_base_url = '';

    /* Use [loginURL] to display the complete login URL; If you want to add more details, you can ignore this variable and modify directly the 'send_login_email' function */
    private $email_html_body = '<p>You can <a href="[loginURL]">click here</a> to login, or just copy and paste the following URL to your browser:</p><p>[loginURL]</p>';

    /* Seconds the login code is valid for; the default value is 600 seconds (10 minutes) */
    private $seconds_code_is_valid_for = 600;

    /* Will check if someone is requesting too many login codes within this time frame; the default value is 600 seconds (10 minutes) */
    private $seconds_to_check_for_spam = 600;

    /* Number of requests allowed per user and user ip within the allowed time; the default value is 3 */
    private $number_of_requests_per_user_and_ip = 3;

    /* A unique string to be used as salt when the login code is generated */
    private $salt = '';

    /* A set of error messages */
    private $error_messages = array('email_not_valid' => 'The email is not valid.',
                                    'user_not_registered' => 'The email does not belong to a register user.',
                                    'stop_spamming' => 'There were too many requests for your email or from your IP address, please try again in a few minutes.',
                                    'login_code_incorrect' => 'The login code is incorrect.',
                                    'login_code_used' => 'The login code was used before.',
                                    'login_code_expired' => 'The login code is expired.',
                                    'generic_error' => 'Something went wrong; please try again.');
	
    /* A set of success messages */
    private $success_messages = array('email_sent' => 'An email with a login URL was sent.',
                                      'valid_code' => 'The code is valid; proceed with login.');
	
    /**
    * Initialises an instance of PDO (for MySQL) to connect to the database
    */
    public function __construct()
    {
        try
        {
            $this->pdo = new PDO("mysql:host=$this->db_host;dbname=$this->db_name", $this->db_username, $this->db_password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }
    }

    /**
    * After an email validation, generates a unique login code, and sends it to the user's email
    *
    * @param string $email an email address e.g. "info@example.com"
    * @return array[boolean][string] boolean: indicating if the process was successfull; string: a message
    */
    public function request_login_code($email)
    {
        $validation = $this->validate_email($email);

        $response = array('was_successfull' => false,
                          'message' => '');

        if (!$validation['is_valid'])
        {
            $response['was_successfull'] = false;
            $response['message'] = $validation['error_message'];
        }
        else
        {
            $ip = $_SERVER["REMOTE_ADDR"];
            $generated_time = time();
			
            if($this->is_user_spamming($email, $generated_time, $ip))
            {
                $response['was_successfull'] = false;
                $response['message'] = $this->error_messages['stop_spamming'];
                return $response;
            }

            $login_code = $this->generate_code($email, $generated_time);
            $record_created = $this->create_login_record($email, $generated_time, $login_code, $ip);

            if($record_created)
            {
                $mail_sent = $this->send_login_email($email, $login_code);
                if($mail_sent)
                {
                    $response['was_successfull'] = true;
                    $response['message'] = $this->success_messages['email_sent'];
                }
                else
                {
                    $response['was_successfull'] = false;
                    $response['message'] = $this->error_messages['generic_error'];
                }
            }
            else
            {
                $response['was_successfull'] = false;
                $response['message'] = $this->error_messages['generic_error'];
            }
        }

        return $response;
    }

    /**
    * Validates an email address
    *
    * @param string $email an email address e.g. "info@example.com"
    * @return array[boolean][string] boolean: is valid or not; string: an error message
    */
    private function validate_email($email)
    {
        $response = array('is_valid' => true,
                          'error_message' => '');

        if(!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            $response['is_valid'] = false;
            $response['error_message'] = $this->error_messages['email_not_valid'];
        }
        else if (!$this->is_registered_user($email))
        {
            $response['is_valid'] = false;
            $response['error_message'] = $this->error_messages['user_not_registered'];
        }

        return $response;
    }

    /**
    * Checks if an email belongs to a registered user.
    *
    * The query needs to be altered based on the architecture of the system this class is integrated to.
    * Afterwards, the "return true;" line should be removed.
    *
    * @param string $email an email address e.g. "info@example.com".
    * @return boolean
    */
    private function is_registered_user($email)
    {
        // After modifying the query below, comment or remove the next line ("return true;") for this function to work properly.
        return true;
		
        try
        {
            // Replace the query with the appropriate query based on your equivalent users table.
            $st = $this->pdo->prepare('SELECT id
                                       FROM users
                                       WHERE email = :email');

            $st->bindParam(':email', $email);

            $st->execute();

            if($st->rowCount() === 1)
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }
    }

    /**
    * Checks if someone is requesting too many login codes within a specific time frame
    *
    * @param string $email an email address e.g. "info@example.com".
    * @param int $generated_time
    * @param string $ip an IP address
    * @return boolean
    */
    private function is_user_spamming($email, $generated_time, $ip)
    {
        try
        {
            $minimun_generated_time = $generated_time - $this->seconds_to_check_for_spam;

            $st = $this->pdo->prepare('SELECT id
                                       FROM ' . $this->db_table_name . '
                                       WHERE (email = :email OR ip = :ip) AND generated_at >= :minimun_generated_time');

            $st->bindParam(':email', $email);
            $st->bindParam(':ip', $ip);
            $st->bindParam(':minimun_generated_time', $minimun_generated_time);

            $st->execute();

            if($st->rowCount() >= $this->number_of_requests_per_user_and_ip)
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }
    }

    /**
    * Generates a unique code based on the user's email and the time the request was made.
    *
    * @param string $email an email address e.g. "info@example.com".
    * @param int $generated_time
    * @return string the generated code
    */
    private function generate_code($email, $generated_time)
    {
        return sha1($email . $this->salt . $generated_time);
    }

    /**
    * Inserts the user's email, the generated code & time in the database.
    *
    * @param string $email an email address e.g. "info@example.com".
    * @param int $generated_time
    * @param string $login_time
    * @param string $ip an IP address
    * @return boolean
    */
    private function create_login_record($email, $generated_time, $login_code, $ip)
    {
        try
        {
            $minimun_generated_time = $generated_time - $this->seconds_to_check_for_spam;

            $st = $this->pdo->prepare('INSERT INTO ' . $this->db_table_name . '
                                       (email, login_code, generated_at, ip, was_used)
                                       VALUES (:email, :login_code, :generated_time, :ip, 0)');

            $st->bindParam(':email', $email);
            $st->bindParam(':login_code', $login_code);
            $st->bindParam(':generated_time', $generated_time);
            $st->bindParam(':ip', $ip);

            return $st->execute();
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }
    }

    /**
    * Sends an email containing the login code using PHPMailer.
    *
    * @param string $email an email address e.g. "info@example.com".
    * @param string $login_code
    * @return boolean
    */
    private function send_login_email($email, $login_code)
    {
        require_once($this->phpmailer_folder . 'class.phpmailer.php');
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->Host = $this->email_host;
        if($this->email_port !== 0)
        {
            $mail->Port = (int) $this->email_port;
        }
        $mail->SMTPAuth = true;
        $mail->Username = $this->email_username;
        $mail->Password = $this->email_password;
        $mail->From = $this->email_from_email;
        $mail->FromName = $this->email_from_name;
        $mail->AddReplyTo($this->email_from_email);
        $mail->WordWrap = 50;
        $mail->IsHTML(true);
        $mail->AddAddress($email);
        $mail->Subject = $this->email_subject;

        $login_url = $this->login_base_url . $login_code;

        $mail->Body = str_replace('[loginURL]', $login_url, $this->email_html_body);

        return $mail->Send();
    }

    /**
    * Checks if the login code is valid.
    *
    * @param string $login_code
    * @return array[boolean][string][string] boolean: code is valid; string: a message; string: the email associated with the login code
    */
    public function is_login_valid($login_code)
    {
        $details = $this->get_login_code_details($login_code);

        $response = array('is_valid' => false,
                          'message' => '',
                          'email' => '');

        if(!empty($details['email']))
        {
            if(!$details['was_used'])
            {
                $generated_time_difference = time() - (int) $details['generated_at'];
                if($generated_time_difference <= $this->seconds_code_is_valid_for)
                {
                    $update_ok = $this->set_login_code_used($login_code);
                    if($update_ok)
                    {
                        $response = array('is_valid' => true,
                                          'message' => $this->success_messages['valid_code']);
                    }
                    else
                    {
                        $response = array('is_valid' => false,
                                          'message' => $this->error_messages['generic_error']);
                    }
                }
                else
                {
                    $response = array('is_valid' => false,
                                      'message' => $this->error_messages['login_code_expired']);
                }
            }
            else
            {
                $response = array('is_valid' => false,
                                  'message' => $this->error_messages['login_code_used']);
            }
            $response['email'] = $details['email'];
        }
        else
        {
            $response = array('is_valid' => false,
                              'message' => $this->error_messages['login_code_incorrect'],
                              'email' => '');
        }

        return $response;
    }

    /**
    * Returns details associated with a login code.
    *
    * @param string $login_code
    * @return array[string][boolean][int] string: the email associated with the login code; boolean: code was used; int: code generated at time
    */
    private function get_login_code_details($login_code)
    {
        $email = '';
        $was_used = null;
        $generated_at = 0;

        try
        {
            $st = $this->pdo->prepare('SELECT email, was_used, generated_at
                                       FROM ' . $this->db_table_name . '
                                       WHERE login_code = :login_code');

            $st->bindParam(':login_code', $login_code);

            $st->execute();
			
            while($row = $st->fetch())
            {
                $email = $row->email;
                $was_used = (bool) $row->was_used;
                $generated_at = (int) $row->generated_at;
            }
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }

        return array('email' => $email,
                     'was_used' => $was_used,
                     'generated_at' => $generated_at);
    }

    /**
    * Sets a login code as used.
    *
    * @param string $login_code
    * @return boolean
    */
    private function set_login_code_used($login_code)
    {
        try
        {
            $st = $this->pdo->prepare('UPDATE ' . $this->db_table_name . '
                                       SET was_used = 1
                                       WHERE login_code = :login_code');

            $st->bindParam(':login_code', $login_code);

            return $st->execute();
        }
        catch(PDOException $e)
        {
            $this->catchException($e->getMessage());
        }
    }

    /**
    * Deals with an exception (prints the exception message)
    *
    * @param string $message
    */
    private function catchException($message)
    {
        echo $message;
    }
}
?>