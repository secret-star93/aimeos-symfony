<?php

setlocale( LC_ALL, 'en_US.UTF-8' );

// if the bundle is within a symfony project, try to reuse the project's autoload
$files = array(
	__DIR__ . '/../vendor/autoload.php',
	__DIR__ . '/../../vendor/autoload.php',
);

$autoload = false;
foreach( $files as $file )
{
	if( is_file( $file ) ) {
		$autoload = include_once $file;
		break;
	}
}

if( !$autoload )
{
	exit(
	"Unable to find autoload.php file, please use composer to load dependencies:
	wget http://getcomposer.org/composer.phar
	php composer.phar install
	Visit http://getcomposer.org/ for more information.\n"
	);
}

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;

AnnotationRegistry::registerLoader( array( $autoload, 'loadClass' ) );
