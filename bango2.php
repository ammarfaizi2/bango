<?php

namespace Bango;

use stdClass;

print "Enter your email: ";
$email = trim(fgets(STDIN, 1024));
$email = explode("@", $email);
for ($i=0; $i < 100; $i++) {
	$fixedEmail = sprintf("%s+%d@%s", $email[0], $i, $email[1]);
	Bango::log("Processing %s...", $fixedEmail);
	$bango = new Bango($fixedEmail);
	$registerData = print_r($bango->generateRegisterData(), true);
	$res = json_decode($bango->doRegister()->out, true);

	if ($res["code"] === 505) {
		Bango::log("Error!");
		Bango::log("\n%s\n", json_encode($res, JSON_PRETTY_PRINT));
		print "Ketik y untuk skip registrasi: ";
		$force = trim(fgets(STDIN, 1024));
		if ($force === "Y" || $force === "y") {
			goto next_d;	
		}
		Bango::log("Aborted!");
		continue;
	} else {
		Bango::log("\n%s\n", json_encode($res, JSON_PRETTY_PRINT));
	}

	if (!isset($res["url"])) {
		$res["url"] = "https://www.bango.co.id/questionnaire/cek/131278";
	}
	$res = $bango->nextPage($res["url"]);
	print $registerData;
	file_put_contents("bango_accounts.txt", $registerData, FILE_APPEND);
	print $res->out;

	next_d:
	$res = $bango->answerQuestion();
	Bango::log("Done");
}


/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com> https://www.facebook.com/ammarfaizi2
 * @license MIT
 * @package \Bango
 */
final class Bango
{
	/**
	 * @var string
	 */
	private $email;

	/**
	 * @var array
	 */
	private $registerData = [];

	/**
	 * @var string $hash
	 */
	private $hash;

	/**
	 * @param string $email
	 *
	 * Constructor.
	 */
	public function __construct($email)
	{
		$this->email = strtolower(trim($email));
		$this->hash = sha1($email);
		is_dir(__DIR__."/cookies") or mkdir(__DIR__."/cookies");
	}

	/**
	 * @return array
	 */
	public function generateRegisterData()
	{
		$this->registerData = [
			"fname" => self::rstr(rand(8, 10)),
			"lname" => self::rstr(rand(8, 10)),
			"dob_tgl" => rand(1, 28),
			"dob_bln" => rand(1, 12),
			"dob_thn" => rand(1980, 1996),
			"gender" => ["male", "female"][rand(0, 1)],
			"email" => $this->email,
			"phone" => "08571".((string)rand(10000000, 99999999)),
			"address" => self::rstr(rand(20, 30)),
			"city" => "Jakarta",
			"zipcode" => rand(40000, 99999),
			"fjbkey" => "",
			"password" => "ABCabc123!@#asdqweASDQWE",
			"cpassword" => "ABCabc123!@#asdqweASDQWE",
			"privacypolicy" => 1,
			"consent" => 1
		];
		return $this->registerData;
	}

	/**
	 * @return \stdClass
	 */
	public function doRegister()
	{
		self::log("Visiting main page...");
		$this->exe("https://www.bango.co.id/");
		self::log("Visiting /auth/register page...");
		$this->exe("https://www.bango.co.id/auth/register");
		self::log("Submitting data to /auth/register page...");
		return $this->exe("https://www.bango.co.id/auth/save_register",
			[
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => http_build_query($this->registerData),
				CURLOPT_HTTPHEADER => [
					"X-Requested-With: XMLHttpRequest"
				]
			]
		);
	}

	/**
	 * @param string $next
	 * @return \stdClass
	 */
	public function nextPage($next)
	{
		self::log("Visiting the next page %s...", $next);
		return $this->exe($next);
	}

	/**
	 * @return \stdClass
	 */
	public function answerQuestion()
	{

		$answers = [
			"id_q=1&id_a=3&multi=no&tipe=radio",
			"id_q=2&id_a=4%2C5%2C19%2C20&multi=yes&tipe=sorting",
			"id_q=3&id_a=7&multi=no&tipe=choice",
			"id_q=4&id_a=10&multi=no&tipe=choice",
			"id_q=5&id_a=14&multi=no&tipe=choice"
		];

		foreach ($answers as $k => $answer) {
			self::log("Answering question %d", $k+1);
			$this->exe("https://www.bango.co.id/questionnaire");
			$o = $this->exe("https://www.bango.co.id/questionnaire/answer",
				[
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $answer
				]
			);
			self::log("Response: %s", $o->out);
			unset($answers[$k]);
		}

		self::log("Visiting result...");
		return $this->exe("https://www.bango.co.id/questionnaire/result");
	}

	/**
	 * @return bool
	 */
	public function deleteCookie()
	{
		return unlink(__DIR__."/cookies/".$this->hash);
	}

	/**
	 * @param string $url
	 * @param array  $opt
	 * @return \stdClass
	 */
	private function exe($url, $opt = [])
	{
		$ch = curl_init($url);
		$optf = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_COOKIEJAR => __DIR__."/cookies/".$this->hash,
			CURLOPT_COOKIEFILE => __DIR__."/cookies/".$this->hash,
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:66.0) Gecko/20100101 Firefox/66.0"
		];

		foreach ($opt as $k => $v) {
			$optf[$k] = $v;
		}

		curl_setopt_array($ch, $optf);
		$o = new stdClass;
		$o->out = curl_exec($ch);
		$o->error = curl_error($ch);
		$o->errno = curl_errno($ch);
		$o->info = curl_getinfo($ch);
		curl_close($ch);

		return $o;
	}

	/**
	 * @param string $format
	 * @param mxied  ...$args
	 * @return void
	 */
	public static function log($format)
	{
		$args = func_get_args();
		array_shift($args);
		printf("[%s]: %s\n",
			date("Y-m-d H:i:s"),
			vsprintf($format, $args)
		);
	}

	/**
	 * @param int $n
	 * @return string
	 */
	public static function rstr($n = 32)
	{
		static $chrlist = "qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM   ";
		$n = abs($n);
		$len = strlen($chrlist) - 1;
		$r = "";
		for ($i=0; $i < $n; $i++) {
			$r .= $chrlist[rand(0, $len)];
		}
		return $r;
	}
}
