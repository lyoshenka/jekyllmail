<?php
error_reporting(-1);
ini_set('display_errors', 1);

chdir(__DIR__);
define('HOME', getcwd());
putenv('HOME=' . HOME);

$xml = simplexml_load_string(file_get_contents('php://input'));

switch($xml->methodName)
{
	//wordpress blog verification
	case 'mt.supportedMethods':
		success('metaWeblog.getRecentPosts');

	//first authentication request from ifttt
	case 'metaWeblog.getRecentPosts':
		//send a blank blog response
		//this also makes sure that the channel is never triggered
		success('<array><data></data></array>');

	case 'metaWeblog.newPost':
		//@see http://codex.wordpress.org/XML-RPC_WordPress_API/Posts#wp.newPost

    $data = parseXmlData($xml);
		if($data)
    {
      createNewPost($data);
			success();
    }
		else
    {
			failure(400);
    }
}

failure(404);



function parseXmlData($xml)
{
    //{
    //"categories": [ "tags", "galore", "bond" ],
    //"description": "oiarnsotyun wfyt nars tafwy tya srt arsta\n\ntnwftna sr tarst arstwftwftsra\n\n\nstwft",
    //"pass": "password",
    //"post_status": "publish",
    //"title": "otywufntrs",
    //"user": "username"
    //}

    $fields = array();
    $fields['user'] = (string)$xml->params->param[1]->value->string;
    $fields['pass'] = (string)$xml->params->param[2]->value->string;

    //@see content in the wordpress docs
    $content = $xml->params->param[3]->value->struct->member;
    foreach($content as $data)
    {
        $field = (string)$data->name;
        $value = null;

        switch($field)
        {
            //the passed tags and categories are parsed into an array
            case 'mt_keywords':
            case 'categories':
                $value = array();
                foreach($data->xpath('value/array/data/value/string') as $cat)
                {
                    $value[] = (string)$cat;
                }
                if ($field == 'mt_keywords')
                {
                    $field = 'tags';
                }
                break;

            //this is used for title/description
            default:
                $value = (string)$data->value->string;
        }

        $fields[$field] = $value;
    }

    return $fields;
}

function createNewPost($data)
{
//    ensureGitDir();
    $title = $data['title'];
    $body = $data['description'];

    $filename = exec(HOME . '/blog/bin/newpost_no_vi ' . escapeshellarg($title), $output, $return);

    if (!$return) // 0 = success
    {
        file_put_contents($filename, $body, FILE_APPEND);

        require 'vendor/autoload.php';
        #$autoloader = new SplClassLoader('PHPGit', HOME . '/php-git-repo/lib');
        #$autoloader->setNamespaceSeparator('_');
        #$autoloader->register();

        $repo = new PHPGit_Repository(HOME . '/blog');
        $repo->git('add -A _posts');
        $repo->git('commit -m "New post via email"');
        $repo->git('push');
    }
}
/*
function ensureGitDir()
{
    if (is_dir(HOME . '/blog'))
    {
        return;
    }
    ensureHerokuKey();
    exec('git clone git@heroku.com:grinfit.git ' . HOME . '/blog');
}
*/

/*
function ensureHerokuKey()
{
    if (file_exists(HOME . '/.ssh/id_rsa'))
    {
        return;
    }

    $key = getenv('heroku_key');
    if (!$key)
    {
        echo "NO HEROKU KEY";
        die();
    }

    mkdir(HOME . '/.ssh');
    file_put_contents(HOME . '/.ssh/id_rsa', "-----BEGIN RSA PRIVATE KEY-----\n" . $key . "\n-----END RSA PRIVATE KEY-----");
    chmod(HOME . '/.ssh/id_rsa', 0600);
}
*/

/** Copied from wordpress */

function success()
{
    output(
        '<?xml version="1.0"?><methodResponse><params>' .
        '<param><value><string>200</string></value></param>' .
        '</params></methodResponse>'
    );
}

function failure($status)
{
    output(
        '<?xml version="1.0"?><methodResponse><fault><value><struct>' .
        '<member><name>faultCode</name><value><int>' . $status .
        '</int></value></member><member><name>faultString</name><value>' .
        '<string>Request was not successful.</string></value></member>' .
        '</struct></value></fault></methodResponse>'
    );
}

function output($xml)
{
    $length = strlen($xml);
    header('Connection: close');
    header('Content-Length: '.$length);
    header('Content-Type: text/xml');
    header('Date: '.date('r'));
    echo $xml;
    exit;
}
