<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;

use App\Models\User;

class UsersController extends BaseController
{

	public function exists(Request $request) {
		if ($request->has('email') && $request->get('email')) {
			$user = User::where('email', $request->get('email'))->first();
		} elseif ($request->has('username') && $request->get('username')) {
			$user = User::where('username', $request->get('username'))->first();
		} else {
			return [
				'success' => false,
				'errors' => ['invalidRequest' => 'Invalid Request']
			];
		}
		return [
			'success' => true,
			'exists' => (boolean) $user
		];
	}

	public function register(Request $request) {
		$data = $request->json();
		$errors = [];
		if (!$data->has('email')) {
			$errors['missing'][] = 'email';
		} elseif (!filter_var($data->get('email'), FILTER_VALIDATE_EMAIL)) {
			$errors['invalid'][] = 'email';
		}
		if (!$data->has('username')) {
			$errors['missing'][] = 'username';
		} elseif (preg_match('/[a-z][a-z0-9\._]{3,23}/i', $data->get('username')) !== 1) {
			$errors['invalid'][] = 'username';
		}
		if ($data->has('email') && $data->has('username')) {
			$dups = app('db')->table('users')->select('email', 'username')->where('email', $data->get('email'))->orWhere('username', $data->get('username'))->limit(2)->get();
			foreach ($dups as $dup) {
				if ($dup->email === $data->get('email')) {
					$errors['duplicates'][] = 'email';
				}
				if ($dup->username === $data->get('username')) {
					$errors['duplicates'][] = 'username';
				}
			}
		}
		if (!$data->has('password')) {
			$errors['missing'][] = 'password';
		} elseif (strlen($data->get('password')) < 8) {
			$errors['invalid'][] = 'password';
		}

		if (sizeof($errors)) {
			return [
				'success' => false,
				'errors' => $errors
			];
		}

		$newUser = new User();
		$newUser->email = $data->get('email');
		$newUser->username = $data->get('username');
		$newUser->password = $data->get('password');
		$newUser->joinDate = \Carbon\Carbon::now();

		try {
			// $newUser->save();
		} catch (\Exception $e) {
			return [
				'success' => false,
				'errors' => ['creatingUser' => true]
			];
		}

		// $latestPost = app('db')->select('SELECT MAX(postID) latestPost FROM posts');
		// app('db')->table('forums_readData_forums')->insert([
		// 	'userID' => $newUser->userID,
		// 	'forumID' => 0,
		// 	'markedRead' => $latestPost[0]->latestPost,
		// ]);
		//
		// $newUser->sendActivationEmail();
		// mail('contact@gamersplane.com', 'New User', 'New User: ' . $newUser->username, 'From: noone@gamersplane.com');

		return [
			'success' => true,
			'user' => [
				'userID' => $newUser->userID,
				'username' => $newUser->username,
				'email' => $newUser->email,
			]
		];
	}

	public function activationLink(Request $request) {
		
	}

	public function index(Request $request)
	{
		$rGames = Game::query();
		if ($request->has('system') && $request->get('system')) {
			$rGames->where('system', $request->get('system'));
		}
		if ($request->has('orderBy')) {
			$rGames->orderBy($request->get('orderBy'), $request->has('orderByDir') ? $request->get('orderByDir') : 'asc');
		}
		if ($request->has('limit') && (int) $request->get('limit')) {
			$rGames->limit((int) $request->get('limit'));
		}

		if ($request->has('fields')) {
			$fields = explode(',', $request->get('fields'));
		} else {
			$fields = [
				"title",
				"gameId",
				"system",
				"created",
				"start",
				"end",
				"postFrequency",
				"numPlayers",
				"charsPerPlayer",
				"description",
				"charGenInfo",
				"status",
				"public",
				"retired",
				"gm",
				"allowedCharSheets",
				"forumId",
				"groupId",
				"players",
				"decks",
			];
		}

		$rGames = $rGames->get();

		$games = [];
		foreach ($rGames as $rGame) {
			$game = [];
			foreach ($fields as $field) {
				$game[$field] = $rGame->$field;
				if (array_search($field, ['created', 'start', 'end', 'retired']) !== false) {
					$game[$field] = $game[$field] ? $game[$field]->timestamp : null;
				}
				if ($field === 'decks') {
					foreach ($game['decks'] as $dKey => $deck) {
						$game['decks'][$dKey]['lastShuffle'] = $game['decks'][$dKey]['lastShuffle']->timestamp ? $game['decks'][$dKey]['lastShuffle']->timestamp : null;
					}
				}
			}
			if (array_search('playerCount', $fields) !== false) {
				$game['playerCount'] = 0;
				foreach ($rGame['players'] as $player) {
					if ($player['approved']) {
						$game['playerCount']++;
					}
				}
			}
			$games[] = $game;
		}

		return [
			'success' => true,
			'games' => $games
		];
	}

}
