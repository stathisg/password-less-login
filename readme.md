# PasswordLessLogin

PasswordLessLogin is a PHP implementation of a password-less login system. When a user enters an email address, the system generates a URL containing a unique login code, and sends it to the user via email. When the login URL is clicked, the system verifies the validity of the code to determine if the user will be allowed to log in or not.

## Requirements

* PHP 5.2.0 or higher.
* [PHPMailer](https://github.com/Synchro/PHPMailer)'s *class.phpmailer.php* and *class.smtp.php* classes.
* Any database system supported by PHP's [PDO class](http://www.php.net/manual/en/class.pdo.php).

## How to configure

Initially, you have to download the two PHPMailer classes mentioned in the *Requirements* section.

Afterwards, create the required table in your database, using the script in create_table.sql. You can change the table's name if you wish. The script is written for MySQL. You'll have to make the required changes if you are using any other database system.

Finally, configure the `PasswordLessLogin` class using its configuration variables located at the top of the class.

**Important note**: The class does not check if a user is a registered member by default. To enable this functionality, you will have to modify the `is_registered_user` function (more details included in the function's comments).

The class is now ready to be used.

## How to use

There are two public functions available to use: `request_login_code` and `is_login_valid`.

A very basic example of usage for `request_login_code`:

```php
require_once("PasswordLessLogin.php");
$passLess = new PasswordLessLogin();

$email = 'a valid email address';

/* After an email validation, request_login_code generates a unique login code, and sends it to the provided email */
$passLess->request_login_code($email);
```

A very basic example of usage for `is_login_valid`:

```php
require_once("PasswordLessLogin.php");
$passLess = new PasswordLessLogin();

$code = 'a login code';

$response = $passLess->is_login_valid($code);

if($response['is_valid'])
{
	//proceed with login; the user's email is stored in $response['email']
}
else
{ 
	//display an error message
	echo $response['message'];
}
```