<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once('vendor/autoload.php');

$app = AppFactory::create();

$app->options('/{routes:.+}', function ($req, $res, $args) {
	return $res;
});

$app->add(function ($req, $handler) {
	$res = $handler->handle($req);
	return $res
		->withHeader('Access-Control-Allow-Origin', '*')
		->withHeader('Access-Control-Allow-Headers', '*')
		->withHeader('Access-Control-Allow-Methods', '*')
		->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->get('/cartoons', function ($req, $res, $args) {
	return $res->withJson(read_cartoons());
});

$app->get('/cartoons/metadata', function ($req, $res, $args) {
	$files = read_cartoons();
	$tags = read_tags();
	$transcripts = read_transcripts();

	foreach ($files as $value) {
		$merged[] = [
			'id' => $value,
			'tags' => array_key_exists($value, $tags) ? $tags[$value] : [],
			'transcript' => array_key_exists($value, $transcripts) ? $transcripts[$value] : ''
		];
	}

	return $res->withJson($merged);
});

$app->get('/cartoons/tags', function ($req, $res, $args) {
	$files = read_cartoons();
	$tags = read_tags();

	foreach ($files as $value) {
		$merged[] = [
			'id' => $value,
			'tags' => array_key_exists($value, $tags) ? $tags[$value] : []
		];
	}

	return $res->withJson($merged);
});

$app->post('/cartoons/tags/merge', function ($req, $res, $args) {
	$tags = read_tags();
	$dupp = '/^\d{8}$/';

	foreach ($tags as $key => $value) {
		$match = current(preg_grep($dupp, $value));

		if ($match) {
			$merge = array_values(preg_grep($dupp, $value, PREG_GREP_INVERT));

			if (!empty($merge)) {
				$existing = array_key_exists($match, $tags) ? $tags[$match] : [];
				$tags[$match] = array_unique(array_merge($existing, $merge));
				sort($tags[$match]);

				$matches[$match] = $merge;
			}
		}
	}

	if (count($matches) > 0) {
		write_tags($tags);
	}

	return $res->withJson($matches);
});

$app->post('/cartoons/{id:\d+}/tags', function ($req, $res, $args) {
	$id = $args['id'];
	$tag = strtolower($req->getParsedBody()['tag']);
	$tags = read_tags();

	if (!array_key_exists($id, $tags) || !in_array($tag, $tags[$id])) {
		$tags[$id][] = $tag;
		write_tags($tags);
	}

	return $res->withJson($tags[$id]);
});

$app->delete('/cartoons/{id:\d+}/tags/{tag}', function ($req, $res, $args) {
	$id = $args['id'];
	$tag = strtolower($args['tag']);
	$tags = read_tags();

	if (in_array($tag, $tags[$id])) {
		array_splice($tags[$id], array_search($tag, $tags[$id]), 1);
		write_tags($tags);
	}

	return $res->withJson($tags[$id]);
});

$app->run();

function get_id($text) {
	if (preg_match('/\/(\d+)\.gif$/', $text, $matches) === 1) {
		return $matches[1];
	} else {
		return $text;
	}
}

function read_cartoons() {
	return array_map('get_id', glob('../../archive/**/*.gif'));
}

function read_transcripts() {
	return json_decode(file_get_contents('data/transcripts.json'), true);
}

function read_tags() {
	return json_decode(file_get_contents('data/tags.json'), true);
}

function write_tags($tags) {
	file_put_contents('data/tags.json', json_encode($tags, JSON_PRETTY_PRINT));
}
