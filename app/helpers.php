<?php
function generateSlug($value) {
	$value = strtolower($value);
	$value = preg_replace('#\s#', '-', $value);
	$value = preg_replace('#[^\w-]#', '', $value);

	return $value;
}

function mongoDTtoCarbon($date) {
	return $date ? \Carbon\Carbon::instance($date->toDateTime())->setTimezone('UTC') : null;
}
