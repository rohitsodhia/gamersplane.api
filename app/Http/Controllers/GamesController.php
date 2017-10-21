<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

use Illuminate\Http\Request;

use App\Models\Game;

class GamesController extends BaseController
{

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
