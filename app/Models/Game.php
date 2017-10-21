<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Game extends Eloquent
{
	protected $connection = 'mongo';
	public $timestamps = false;
	protected $casts = [
		'gameId' => 'integer',
		'numPlayers' => 'integer',
		'forumId' => 'integer',
		'groupId' => 'integer',
		'public' => 'boolean',
	];

	function getCreatedAttribute($date) {
		return \mongoDTtoCarbon($date);
	}

	function getStartAttribute($date) {
		return \mongoDTtoCarbon($date);
	}

	function getEndAttribute($date) {
		return \mongoDTtoCarbon($date);
	}

	function getRetiredAttribute($date) {
		return \mongoDTtoCarbon($date);
	}

	function getDecksAttribute($decks) {
		foreach ($decks as $key => $deck) {
			$decks[$key]['lastShuffle'] = \mongoDTtoCarbon($deck['lastShuffle']);
		}

		return $decks;
	}

}
