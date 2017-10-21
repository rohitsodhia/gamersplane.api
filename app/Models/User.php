<?php

namespace App\Models;

use \Exception;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class User extends Model
{

	protected $primaryKey = 'userID';
	public $timestamps = false;

	public function __construct()
	{
	}

	public function setPasswordAttribute($password)
	{
		$this->attributes['password'] = password_hash($password, PASSWORD_DEFAULT);
	}

	public function sendActivationEmail() {
		$message = "Thank you for registering for Gamers Plane!\n\n";
		$message .= "Please click on the following link to activate your account:\n";
		$message .= '<a href="https://gamersplane.com/register/activate/' . md5($this->username) . "\">Activate account</a>\n";
		$message .= 'Or copy and paste this URL into your browser: https://gamersplane.com/register/activate/' . md5($this->username) . "/\n\n";
		$message .= 'Please do not respond to this email, as it will be ignored';
		$mailSent = false;
		if (env('APP_ENV') !== 'local') {
			do {
				$mailSent = mail($this->email, 'Gamers Plane Activation Required', $message, 'From: contact@gamersplane.com');
			} while (!$mailSent);
		}
	}

	public function getLoginHash()
	{
		return substr(hash('sha256', getenv('PVAR') . $this->email . $this->joinDate), 7, 32);
	}

	public function generateLoginCookie()
	{
		setcookie('loginHash', '', time() - 30, '/', getenv('APP_COOKIE_DOMAIN'));
		setcookie('loginHash', $this->username . '|' . $this->getLoginHash(), time() + (60 * 60 * 24 * 7), '/', getenv('APP_COOKIE_DOMAIN'));
	}

	public static function logout($resetSession = false)
	{
		if ($resetSession) {
			session_unset();
			// unset($_COOKIE[session_name()]);

			session_regenerate_id(TRUE);
			session_destroy();
			setcookie(session_name(), '', time() - 30, '/');
			$_SESSION = [];
		}

		setcookie('loginHash', '', time() - 30, '/', getenv('APP_COOKIE_DOMAIN'));
		// session_destroy();
	}

	public function metadata() {
		return $this->hasMany(Usermeta::class, 'userID');
	}

	public function getUsermeta($metaKey)
	{
		$usermeta = Usermeta::where('userID', $this->userID)->where('metaKey', $metaKey)->first();

		if ($usermeta) {
			$this->usermeta[$metaKey] = $usermeta;
			return $this->usermeta[$metaKey];
		} else {
			return null;
		}
	}

	public function getAllUsermeta()
	{
		$usermetas = Usermeta::where('userID', $this->userID)->where('autoload', 0)->get();
		foreach ($usermetas as $usermeta) {
			$this->usermeta[$usermeta->metaKey] = $usermeta;
		}

		return true;
	}

	public function updateUsermeta($metaKey, $metaValue, $autoload = false)
	{
		if ($metaValue !== null && $metaValue !== '') {
			$autoload = $autoload === true ? 1 : 0;
			$updateUsermeta = Usermeta::updateOrCreate(
				[
					'userID' => $this->userID,
					'metaKey' => $metaKey
				],
				[
					'metaValue' => is_array($metaValue) ? serialize($metaValue) : $metaValue,
					'autoload' => $autoload
				]
			);

			$this->usermeta[$metaKey] = $updateUsermeta;
		} else {
			$this->deleteUsermeta($metaKey);
		}

		return true;
	}

	public function deleteUsermeta($metaKey)
	{
		Usermeta::where('userID', $this->userID)->where('metaKey', $metaKey)->limit(1)->delete();
		unset($this->usermeta[$metaKey]);
	}

	static function getAvatar($userID, $ext = false, $exists = false)
	{
		$userID = (int) $userID;
		if ($userID <= 0) {
			return $exists ? false : '/ucp/avatars/avatar.png';
		}

		if (!$ext) {
			$mysql = DB::conn('mysql');

			$ext = $mysql->query("SELECT metaValue FROM usermeta WHERE userID = {$userID} AND metaKey = 'avatarExt'");
			if ($ext->rowCount()) {
				$ext = $ext->fetchColumn();
			} else {
				$ext = false;
			}
		}
		if ($ext !== false && file_exists(FILEROOT . "/ucp/avatars/{$userID}.{$ext}")) {
			return $exists ? true : "/ucp/avatars/{$userID}.{$ext}";
		} else {
			return $exists ? false : '/ucp/avatars/avatar.png';
		}
	}

	public function checkACP($role, $redirect = true)
	{
		if ($role == 'all' && sizeof($this->acpPermissions)) {
			return $this->acpPermissions;
		} elseif ($role == 'any' && sizeof($this->acpPermissions)) {
			return true;
		} else {
			if (!$redirect && ($this->acpPermissions == null || (!in_array($role, $this->acpPermissions) && !in_array('all', $this->acpPermissions)))) {
				return false;
			} elseif ($this->acpPermissions == null) {
				header('Location: /');
				exit;
			} elseif (!in_array($role, $this->acpPermissions) && !in_array('all', $this->acpPermissions)) {
				header('Location: /acp/');
				exit;
			} else {
				return true;
			}
		}
	}

	static public function inactive($lastActivity, $returnImg = true)
	{
		$diff = time() - strtotime($lastActivity);
		$diff = floor($diff / (60 * 60 * 24));
		if ($diff < 14) {
			return false;
		}
		$diffStr = 'Inactive for';
		if ($diff <= 30) {
			$diffStr .= ' '.($diff - 1).' days';
		} else {
			$diff = floor($diff / 30);
			if ($diff < 12) {
				$diffStr .= ' ' . $diff . ' months';
			} else {
				$diffStr .= 'ever!';
			}
		}
		return $returnImg ? "<img src=\"/images/sleeping.png\" title=\"{$diffStr}\" alt=\"{$diffStr}\">" : $diffStr;
	}
}

// class UserObserver
// {
//
// 	public function created(User $user) {
// 		dd($user);
// 		error_log(print_r($user, true));
// 	}
//
// }
