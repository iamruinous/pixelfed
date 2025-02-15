<?php

namespace App\Services;

use Cache;
use App\Profile;
use App\Util\Webfinger\WebfingerUrl;
use Illuminate\Support\Facades\Http;
use App\Util\ActivityPub\Helpers;
use App\Services\AccountService;

class WebfingerService
{
	public static function lookup($query)
	{
		return (new self)->run($query);
	}

	protected function run($query)
	{
		if($profile = Profile::whereUsername($query)->first()) {
			return AccountService::get($profile->id);
		}
		$url = WebfingerUrl::generateWebfingerUrl($query);
		if(!Helpers::validateUrl($url)) {
			return [];
		}

		$res = Http::retry(3, 100)
			->acceptJson()
			->withHeaders([
				'User-Agent' => '(Pixelfed/' . config('pixelfed.version') . '; +' . config('app.url') . ')'
			])
			->timeout(20)
			->get($url);

		if(!$res->successful()) {
			return [];
		}

		$webfinger = $res->json();
		if(!isset($webfinger['links']) || !is_array($webfinger['links']) || empty($webfinger['links'])) {
			return [];
		}

		$link = collect($webfinger['links'])
			->filter(function($link) {
				return $link &&
					isset($link['type']) &&
					isset($link['href']) &&
					$link['type'] == 'application/activity+json';
			})
			->pluck('href')
			->first();

		$profile = Helpers::profileFetch($link);
		return AccountService::get($profile->id);
	}
}
