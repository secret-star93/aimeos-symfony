<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 * @copyright Aimeos (aimeos.org), 2014
 */


namespace Aimeos\ShopBundle\Composer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Composer\Script\CommandEvent;


/**
 * Performs bundle setup during composer installs
 */
class ScriptHandler
{
	/**
	 * Updates the shop database.
	 *
	 * @param CommandEvent $event CommandEvent instance
	 * @throws \RuntimeException If an error occured
	 */
	public static function updateDatabase( CommandEvent $event )
	{
		$options = array( '--extdir=./ext/' );

		if( $event->isDevMode() ) {
			$options[] = '--option=setup/default/demo:1';
		}

		self::executeCommand( $event, 'aimeos:update', $options );
	}


	/**
	 * Installs the shop bundle.
	 *
	 * @param CommandEvent $event CommandEvent instance
	 * @throws \RuntimeException If an error occured
	 */
	public static function installBundle( CommandEvent $event )
	{
		$event->getIO()->write( 'Installing the Aimeos shop bundle' );

		$options = self::getOptions( $event );

		if( !isset( $options['symfony-app-dir'] ) || !is_dir( $options['symfony-app-dir'] ) )
		{
			$msg = 'An error occurred because the "%1$s" option or the "%2$s" directory isn\'t available';
			throw new \RuntimeException( sprintf( $msg, 'symfony-app-dir', $options['symfony-app-dir'] ) );
		}

		if( !isset( $options['symfony-web-dir'] ) || !is_dir( $options['symfony-web-dir'] ) )
		{
			$msg = 'An error occurred because the "%1$s" option or the "%2$s" directory isn\'t available';
			throw new \RuntimeException( sprintf( $msg, 'symfony-web-dir', $options['symfony-web-dir'] ) );
		}

		self::updateConfigFile( $options['symfony-app-dir'] . '/config/config.yml' );
		self::updateRoutingFile( $options['symfony-app-dir'] . '/config/routing.yml' );
		self::createDirectory( $options['symfony-web-dir'] . '/uploads' );
	}


	/**
	 * Creates a new directory if it doesn't exist yet
	 *
	 * @param string $dir Absolute path of the new directory
	 * @throws \RuntimeException If directory couldn't be created
	 */
	protected static function createDirectory( $dir )
	{
		$perm = 0755;

		if( !is_dir( $dir ) && !mkdir( $dir, $perm, true ) )
		{
			$msg = 'Unable to create directory "%1$s" with permission "%2$s"';
			throw new \RuntimeException( sprintf( $msg, $dir, $perm ) );
		}
	}


	/**
	 * Executes a Symphony command.
	 *
	 * @param CommandEvent $event Command event object
	 * @param string $cmd Command name to execute, e.g. "aimeos:update"
	 * @param array List of configuration options for the given command
	 * @throws \RuntimeException If the command couldn't be executed
	 */
	protected static function executeCommand( CommandEvent $event, $cmd, array $options = array() )
	{
		$php = escapeshellarg( self::getPhp() );
		$console = escapeshellarg( self::getConsoleDir( $event ) . '/console' );
		$cmd = escapeshellarg( $cmd );

		foreach( $options as $key => $option ) {
			$options[$key] = escapeshellarg( $option );
		}

		if( $event->getIO()->isDecorated() ) {
			$console .= ' --ansi';
		}

		$process = new Process( $php . ' ' . $console . ' ' . $cmd . ' ' . implode( ' ', $options ), null, null, null, 3600 );

		$process->run( function( $type, $buffer ) use ( $event ) {
			$event->getIO()->write( $buffer, false );
		} );

		if( !$process->isSuccessful() ) {
			throw new \RuntimeException( sprintf( 'An error occurred when executing the "%s" command', escapeshellarg( $cmd ) ) );
		}
	}



	/**
	 * Returns a relative path to the directory that contains the `console` command.
	 *
	 * @param CommandEvent $event Command event object
	 * @return string The path to the console directory
	 * @throws \RuntimeException If console directory couldn't be found
	 */
	protected static function getConsoleDir( CommandEvent $event )
	{
		$options = self::getOptions( $event );

		if( isset( $options['symfony-bin-dir'] ) && is_dir( $options['symfony-bin-dir'] ) ) {
			return $options['symfony-bin-dir'];
		}

		if( isset( $options['symfony-app-dir'] ) && is_dir( $options['symfony-app-dir'] ) ) {
			return $options['symfony-app-dir'];
		}

		throw new \RuntimeException( sprintf( 'Console directory not found. Neither %1$s nor %2$s option exist', 'symfony-app-dir', 'symfony-bin-dir' ) );
	}


	/**
	 * Returns the available options defined in the composer file.
	 *
	 * @param CommandEvent $event Command event object
	 * @return array Associative list of option keys and values
	 */
	protected static function getOptions( CommandEvent $event )
	{
		return $event->getComposer()->getPackage()->getExtra();
	}


	/**
	 * Returns the path to the PHP interpreter.
	 *
	 * @return string Path to the PHP command
	 * @throws \RuntimeException If PHP interpreter couldn't be found
	 */
	protected static function getPhp()
	{
		$phpFinder = new PhpExecutableFinder;

		if( !( $phpPath = $phpFinder->find() ) ) {
			throw new \RuntimeException( 'The php executable could not be found, add it to your PATH environment variable and try again' );
		}

		return $phpPath;
	}


	/**
	 * Adds the Aimeos shop bundle to the config file of the application.
	 *
	 * @param string $filename Name of the YAML config file
	 * @throws \RuntimeException If file is not found
	 */
	protected static function updateConfigFile( $filename )
	{
		$update = false;

		if( ( $content = file_get_contents( $filename ) ) === false ) {
			throw new \RuntimeException( sprintf( 'File "%1$s" not found', $filename ) );
		}

		if( self::importServices( $content ) === true ) {
			$update = true;
		}

		if( self::importConfig( $content ) === true ) {
			$update = true;
		}

		if( self::addAsseticBundle( $content ) === true ) {
			$update = true;
		}

		if( $update === true )
		{
			$fs = new Filesystem();
			$fs->dumpFile( $filename, $content );
		}
	}


	/**
	 * Adds the Aimeos shop bundle to the routing file of the application.
	 *
	 * @param string $filename Name of the YAML config file
	 * @throws \RuntimeException If file is not found
	 */
	protected static function updateRoutingFile( $filename )
	{
		$update = false;

		if( ( $content = file_get_contents( $filename ) ) === false ) {
			throw new \RuntimeException( sprintf( 'File "%1$s" not found', $filename ) );
		}

		if( strpos( $content, 'aimeos_shop:' ) === false )
		{
			$content .= "\n" . 'aimeos_shop:
    resource: "@AimeosShopBundle/Resources/config/routing.yml"
    prefix: /';

			$update = true;
		}

		if( $update === true )
		{
			$fs = new Filesystem();
			$fs->dumpFile( $filename, $content );
		}
	}


	/**
	 * Adds the config.yml to the config file
	 *
	 * @param string &$content Content of the config.yml file
	 * @return boolean True if modified, false if not
	 */
	protected static function importConfig( &$content )
	{
		if( preg_match( "#    - \{ resource: \"@AimeosShopBundle/Resources/config/config.yml\" \}#", $content ) !== 1 )
		{
			$search = array( "/imports:/" );
			$replace = array( "imports:\n    - { resource: \"@AimeosShopBundle/Resources/config/config.yml\" }" );

			if( ( $content = preg_replace( $search, $replace, $content ) ) !== null ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Adds the services.yml to the config file
	 *
	 * @param string &$content Content of the config.yml file
	 * @return boolean True if modified, false if not
	 */
	protected static function importServices( &$content )
	{
		if( preg_match( "#    - \{ resource: \"@AimeosShopBundle/Resources/config/services.yml\" \}#", $content ) !== 1 )
		{
			$search = array( "/imports:/" );
			$replace = array( "imports:\n    - { resource: \"@AimeosShopBundle/Resources/config/services.yml\" }" );

			if( ( $content = preg_replace( $search, $replace, $content ) ) !== null ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Adds the AimeosShopBundle to the assetic section of the config file
	 *
	 * @param string &$content Content of the config.yml file
	 * @return boolean True if modified, false if not
	 */
	protected static function addAsseticBundle( &$content )
	{
		if( preg_match( "/    bundles:[ ]*\[.*'AimeosShopBundle'.*\]/", $content ) !== 1 )
		{
			$search = array( "/    bundles:[ ]*\[([^\]]+)\]/", "/    bundles:[ ]*\[([ ]*)\]/" );
			$replace = array( "    bundles: [$1,'AimeosShopBundle']", "    bundles: ['AimeosShopBundle']" );

			if( ( $content = preg_replace( $search, $replace, $content ) ) !== null ) {
				return true;
			}
		}

		return false;
	}
}
