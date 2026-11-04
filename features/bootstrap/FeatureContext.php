<?php
/**
 * Behat tests.
 *
 * @package ee-cli
 */

/* Start: Loading required files to enable EE::launch() in tests. */
define( 'EE_ROOT', __DIR__ . '/../..' );

include_once( EE_ROOT . '/php/class-ee.php' );
include_once( EE_ROOT . '/php/EE/Runner.php' );
include_once( EE_ROOT . '/php/utils.php' );

define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );

$root_alias = '/opt';
if ( 'Darwin' === php_uname( 's' ) ) {
	$sys_name   = posix_getpwuid(posix_geteuid())['name'];
	if ( ! empty( $sys_name ) ) {
		$root_alias = '/Users/' . $sys_name;
	}
}
// Package autoload files (e.g. site-command.php) may already define it.
if ( ! defined( 'EE_ROOT_DIR' ) ) {
	define( 'EE_ROOT_DIR', $root_alias . '/easyengine' );
}

require_once EE_ROOT . '/php/bootstrap.php';

if ( ! class_exists( 'EE\Runner' ) ) {
	require_once EE_ROOT . '/php/EE/Runner.php';
}

if ( ! class_exists( 'EE\Configurator' ) ) {
	require_once EE_ROOT . '/php/EE/Configurator.php';
}

$logger_dir = EE_ROOT . '/php/EE/Loggers';
$iterator   = new \DirectoryIterator( $logger_dir );

// Make sure the base class is declared first.
include_once "$logger_dir/Base.php";

foreach ( $iterator as $filename ) {
	if ( '.php' !== pathinfo( $filename, PATHINFO_EXTENSION ) ) {
		continue;
	}

	include_once "$logger_dir/$filename";
}
$runner = \EE::get_runner();
$runner->init_logger();
$runner->init_config();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	/**
	 * Whether this run built ee.phar/ee-old.phar, so the cleanup only removes its own files.
	 *
	 * @var bool
	 */
	private static $phar_built = false;

	public $command;
	public $commands;
	public $webroot_path;
	public $ee_path;

	/**
	 * Only sites starting with this prefix are ever created or deleted by this suite.
	 */
	const SITE_PREFIX = 'bh-log-command-';

	/**
	 * Initializes context.
	 */
	public function __construct()
	{
		$this->commands = [];
		$this->ee_path = getcwd();
	}

	/**
	 * @Given ee phar is generated
	 */
	public function eePharIsPresent()
	{
		// Checks if phar already exists, replaces it
		if(file_exists('ee-old.phar')) {
			// Below exec call is required as currenly `ee cli update` is ran with root
			// which updates ee.phar with root privileges.
			exec("sudo rm ee.phar");
			copy('ee-old.phar','ee.phar');
			self::$phar_built = true;
			return 0;
		}
		exec("php -dphar.readonly=0 utils/make-phar.php ee.phar", $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception("Unable to generate phar" . $return_status);
		}

		// Cache generaed phar as it is expensive to generate one
		copy('ee.phar','ee-old.phar');
		self::$phar_built = true;
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled($command)
	{
		exec("type " . $command, $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception($command . " is not installed! Exit code is:" . $return_status);
		}
	}

	/**
	 * @When /^I run ['"](.*)['"]$/
	 */
	public function iRun($command)
	{
		$this->commands[] = EE::launch($command, false, true);
	}

	/**
	 * @Then return value should be 0
	 */
	public function returnValueShouldBe0()
	{
		if ( 0 !== $this->commands[0]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then return value of command :index should be 0
	 */
	public function returnValueOfCommandShouldBe0($index)
	{
		if ( 0 !== $this->commands[ $index - 1 ]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->command);
		}
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return exactly$/
	 */
	public function stdoutShouldReturnExactly($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return exactly$/
	 */
	public function stdoutOfCommandShouldReturnExactly($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $expected_output->getRaw() !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return something like$/
	 */
	public function stdoutShouldReturnSomethingLike($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}


	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return something like$/
	 */
	public function stdoutOfCommandShouldReturnSomethingLike($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		if (strpos($command_output, $expected_output->getRaw()) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then The site :site should be :type multisite
	 */
	public function theSiteShouldBeMultisite( $site, $type )
	{
		$result = EE::launch( "cd " . EE_SITE_ROOT . "/$site && docker-compose exec --user='www-data' php sh -c 'wp config get SUBDOMAIN_INSTALL'", false, true );

		if( $result->stderr ) {
			throw new Exception("Found error while executing command: $result->stderr");
		}

		if($type === 'subdomain' && trim($result->stdout) !== '1') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be 1. Got: $result->stdout");
		}
		else if($type === 'subdir' && trim($result->stdout) !== '') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be empty. Got: $result->stdout");
		}
		else if($type !== 'subdomain' && $type !== 'subdir') {
			throw new Exception("Found unknown site type: $type");
		}
	}

	/**
	 * @Then The :site db entry should be removed
	 */
	public function theDbEntryShouldBeRemoved($site)
	{
		$out = shell_exec("sudo bin/ee site list");
		if (strpos($out, $site) !== false) {
			throw new Exception("$site db entry not been removed!");
		}

	}

	/**
	 * @Then The :site webroot should be removed
	 */
	public function theWebrootShouldBeRemoved($site)
	{
		if ( file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been removed!");
		}
	}

	/**
	 * @Then Following containers of site :site should be removed:
	 */
	public function followingContainersOfSiteShouldBeRemoved($site, TableNode $table)
	{
		$containers = $table->getHash();
		$site_name = implode(explode('.', $site));

		foreach ($containers as $container) {

			$sevice = $container['container'];
			$container_name = $site_name . '_' . $sevice . '_1';

			exec("docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return);
			if (!$return) {
				throw new Exception("$container_name has not been removed!");
			}
		}
	}

	/**
	 * @Then The site :site should have webroot
	 */
	public function theSiteShouldHaveWebroot($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been created!");
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . '/app/wp-config.php' ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders($site, TableNode $table)
	{
		$url = 'http://' . $site;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with header :header should contain following headers:
	 */
	public function requestOnWithHeaderShouldContainFollowingHeaders($host, $header, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $header ]);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with resolve option :resolve should contain following headers:
	 */
	public function requestOnWithResolveOptionShouldContainFollowingHeaders($host, $resolve, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RESOLVE, [ $resolve ]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$headers = curl_exec($ch);
		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario(AfterScenarioScope $scope)
	{
		$this->commands = [];
		chdir($this->ee_path);
	}

	/**
	 * @Then There should be :expected_running_containers containers with labels
	 */
	public function thereShouldBeContainersWithLabel($expected_running_containers, PyStringNode $pyStringNode)
	{
		$labels = $pyStringNode->getStrings();
		$label_string = implode(' -f label=',$labels);

		$result = EE::launch( "docker ps -qf label=$label_string | wc -l", false, true );
		$running_containers = (int) trim($result->stdout);

		if ( (int) $expected_running_containers !== $running_containers ) {
			throw new Exception("Expected $expected_running_containers running containers. Found: $running_containers");
		}
	}

	/**
	 * Deletes only this suite's sites (never every site) and the cached phars.
	 *
	 * @AfterFeature
	 */
	public static function cleanup( AfterFeatureScope $scope )
	{
		foreach ( self::sitesWithPrefix( self::SITE_PREFIX ) as $site ) {
			EE::launch( 'bin/ee site delete ' . escapeshellarg( $site ) . ' --yes', false, true );
		}

		if ( self::$phar_built ) {
			foreach ( [ 'ee.phar', 'ee-old.phar' ] as $phar ) {
				if ( file_exists( $phar ) ) {
					unlink( $phar );
				}
			}
			self::$phar_built = false;
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Shared generic steps (added for log-command; generic enough to share).
	 *
	 * Paths and expected texts accept the placeholders {EE_ROOT_DIR}
	 * (/opt/easyengine), {SITES_DIR} ({EE_ROOT_DIR}/sites) and {EE_ROOT}
	 * (the core checkout running the suite).
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Files and directories created by steps, removed after each scenario (newest first).
	 *
	 * @var array
	 */
	private $created_paths = [];

	/**
	 * Replaces the path placeholders in a step argument.
	 */
	private function expand( $text )
	{
		return strtr(
			$text,
			[
				'{EE_ROOT_DIR}' => EE_ROOT_DIR,
				'{SITES_DIR}'   => EE_SITE_ROOT,
				'{EE_ROOT}'     => $this->ee_path,
			]
		);
	}

	/**
	 * Runs a query against EasyEngine's sqlite DB (read-only use).
	 */
	private static function dbQuery( $sql, array $params = [] )
	{
		$db_file = EE_ROOT_DIR . '/db/ee.sqlite';
		if ( ! file_exists( $db_file ) ) {
			return [];
		}
		$db = new PDO( 'sqlite:' . $db_file );
		$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$stmt = $db->prepare( $sql );
		$stmt->execute( $params );

		return $stmt->fetchAll( PDO::FETCH_ASSOC );
	}

	private static function sitesWithPrefix( $prefix )
	{
		$sites = array_column( self::dbQuery( 'SELECT site_url FROM sites' ), 'site_url' );

		return array_values(
			array_filter(
				$sites,
				function ( $site ) use ( $prefix ) {
					return 0 === strpos( $site, $prefix );
				}
			)
		);
	}

	private static function siteRow( $site )
	{
		$rows = self::dbQuery( 'SELECT site_url, site_type, site_enabled, site_fs_path FROM sites WHERE site_url = ?', [ $site ] );

		return empty( $rows ) ? null : $rows[0];
	}

	private static function assertOwnSite( $site )
	{
		if ( 0 !== strpos( $site, self::SITE_PREFIX ) ) {
			throw new Exception( "Refusing to manage '$site': this suite only touches sites starting with '" . self::SITE_PREFIX . "'." );
		}
	}

	/**
	 * Write steps may only touch files inside this suite's own site directories.
	 */
	private static function assertOwnPath( $path )
	{
		$own_root = EE_SITE_ROOT . '/' . self::SITE_PREFIX;
		if ( 0 !== strpos( $path, $own_root ) || false !== strpos( $path, '..' ) ) {
			throw new Exception( "Refusing to modify '$path': only paths under '$own_root*' are allowed." );
		}
	}

	private static function stripAnsi( $text )
	{
		return preg_replace( '/\e\[[0-9;]*m/', '', $text );
	}

	private static function describe( $result )
	{
		return sprintf(
			"Command: %s\nReturn code: %s\n--- STDOUT ---\n%s\n--- STDERR ---\n%s",
			$result->command,
			$result->return_code,
			self::stripAnsi( $result->stdout ),
			self::stripAnsi( $result->stderr )
		);
	}

	private function lastCommand()
	{
		if ( empty( $this->commands ) ) {
			throw new Exception( 'No command has been run in this scenario.' );
		}

		return end( $this->commands );
	}

	private function streamOf( $stream )
	{
		$result = $this->lastCommand();

		return self::stripAnsi( 'STDOUT' === $stream ? $result->stdout : $result->stderr );
	}

	/**
	 * Mirrors mkdir -p and remembers what it created.
	 */
	private function makeDir( $dir )
	{
		$missing = [];
		while ( ! is_dir( $dir ) ) {
			$missing[] = $dir;
			$dir       = dirname( $dir );
		}
		foreach ( array_reverse( $missing ) as $new_dir ) {
			if ( ! mkdir( $new_dir ) ) {
				throw new Exception( "Could not create directory $new_dir" );
			}
			$this->created_paths[] = $new_dir;
		}
	}

	/**
	 * Runs a command in its own process group under timeout(1), so long-running followers
	 * (e.g. `tail -f`) are killed together with ee. Exit code 124 means the timeout expired.
	 */
	private function launchWithTimeout( $command, $seconds, $cwd = null, $before = '' )
	{
		// proc_open's cwd resolves symlinks (a site's app/ links to a docker volume), so also pass the
		// logical path as PWD, like an interactive `cd` would; get_site_name() matches on it.
		$timed = sprintf(
			'timeout -k 5 %d env BEHAT_RUN=1%s sh -c %s',
			(int) $seconds,
			null === $cwd ? '' : ' PWD=' . escapeshellarg( $cwd ),
			escapeshellarg( $this->expand( $command ) )
		);
		if ( '' !== $before ) {
			$timed = "$before $timed; rc=\$?; wait; exit \$rc";
		}

		return EE::launch( $timed, false, true, [], null, $cwd );
	}

	/**
	 * @Given the site :site of type :type exists
	 */
	public function theSiteOfTypeExists( $site, $type )
	{
		self::assertOwnSite( $site );
		$row = self::siteRow( $site );

		if ( $row && $row['site_type'] !== $type ) {
			throw new Exception( "Site $site already exists with type {$row['site_type']}, expected $type." );
		}

		if ( ! $row ) {
			$result = EE::launch( 'bin/ee site create ' . escapeshellarg( $site ) . ' --type=' . escapeshellarg( $type ), false, true );
			$row    = self::siteRow( $site );
			if ( 0 !== $result->return_code || ! $row ) {
				throw new Exception( "Could not create site $site.\n" . self::describe( $result ) );
			}
		}

		if ( ! $row['site_enabled'] ) {
			$result = EE::launch( 'bin/ee site enable ' . escapeshellarg( $site ), false, true );
			$row    = self::siteRow( $site );
			if ( ! $row['site_enabled'] ) {
				throw new Exception( "Could not enable site $site.\n" . self::describe( $result ) );
			}
		}
	}

	/**
	 * @Given the site :site is disabled
	 */
	public function theSiteIsDisabled( $site )
	{
		self::assertOwnSite( $site );
		$row = self::siteRow( $site );
		if ( ! $row ) {
			throw new Exception( "Site $site does not exist." );
		}

		if ( $row['site_enabled'] ) {
			$result = EE::launch( 'bin/ee site disable ' . escapeshellarg( $site ), false, true );
			$row    = self::siteRow( $site );
			if ( $row['site_enabled'] ) {
				throw new Exception( "Could not disable site $site.\n" . self::describe( $result ) );
			}
		}
	}

	/**
	 * @Then the site :site should be :state
	 */
	public function theSiteShouldBe( $site, $state )
	{
		$row    = self::siteRow( $site );
		$actual = ! $row ? 'missing' : ( $row['site_enabled'] ? 'enabled' : 'disabled' );
		if ( $actual !== $state ) {
			throw new Exception( "Expected site $site to be $state, it is $actual." );
		}
	}

	/**
	 * @Given the site :site does not exist
	 */
	public function theSiteDoesNotExist( $site )
	{
		self::assertOwnSite( $site );
		if ( self::siteRow( $site ) ) {
			$result = EE::launch( 'bin/ee site delete ' . escapeshellarg( $site ) . ' --yes', false, true );
			if ( self::siteRow( $site ) ) {
				throw new Exception( "Could not delete site $site.\n" . self::describe( $result ) );
			}
		}
	}

	/**
	 * @Given the file :path contains:
	 */
	public function theFileContains( $path, PyStringNode $content )
	{
		$path = $this->expand( $path );
		self::assertOwnPath( $path );
		$this->makeDir( dirname( $path ) );
		if ( ! file_exists( $path ) ) {
			$this->created_paths[] = $path;
		}
		if ( false === file_put_contents( $path, $this->expand( $content->getRaw() ) . "\n" ) ) {
			throw new Exception( "Could not write $path" );
		}
	}

	/**
	 * Plants a link the way the site's own containers could, pointing at a marker file outside the site.
	 *
	 * @Given the file :path is a link to a file outside the site containing :text
	 */
	public function theFileIsALinkToAFileOutsideTheSite( $path, $text )
	{
		$path = $this->expand( $path );
		self::assertOwnPath( $path );
		$this->makeDir( dirname( $path ) );
		$target = tempnam( sys_get_temp_dir(), 'bh-log-outside-' );
		$this->created_paths[] = $target;
		if ( false === file_put_contents( $target, $text . "\n" ) || ! symlink( $target, $path ) ) {
			throw new Exception( "Could not link $path to $target" );
		}
		$this->created_paths[] = $path;
	}

	/**
	 * @Given the file :path is a link to :target
	 */
	public function theFileIsALinkTo( $path, $target )
	{
		$path = $this->expand( $path );
		self::assertOwnPath( $path );
		$this->makeDir( dirname( $path ) );
		if ( ! symlink( $this->expand( $target ), $path ) ) {
			throw new Exception( "Could not link $path to $target" );
		}
		$this->created_paths[] = $path;
	}

	/**
	 * @Given the file :path does not exist
	 */
	public function theFileDoesNotExist( $path )
	{
		$path = $this->expand( $path );
		self::assertOwnPath( $path );
		if ( file_exists( $path ) && ! unlink( $path ) ) {
			throw new Exception( "Could not delete $path" );
		}
	}

	/**
	 * @Then the file :path should exist
	 */
	public function theFileShouldExist( $path )
	{
		$path = $this->expand( $path );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "Expected file $path to exist." );
		}
	}

	/**
	 * @Then the file :path should contain :text
	 */
	public function theFileShouldContain( $path, $text )
	{
		$path = $this->expand( $path );
		$text = $this->expand( $text );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "Expected file $path to exist." );
		}
		$content = file_get_contents( $path );
		if ( false === strpos( $content, $text ) ) {
			throw new Exception( "Expected $path to contain '$text'. Last 2 KB:\n" . substr( $content, -2048 ) );
		}
	}

	/**
	 * @When I run :command with a timeout of :seconds seconds
	 */
	public function iRunWithATimeoutOfSeconds( $command, $seconds )
	{
		$this->commands[] = $this->launchWithTimeout( $command, $seconds );
	}

	/**
	 * Runs from another working directory; a leading `bin/ee` still means this checkout's bin/ee.
	 *
	 * @When I run :command from :dir with a timeout of :seconds seconds
	 */
	public function iRunFromWithATimeoutOfSeconds( $command, $dir, $seconds )
	{
		$dir = $this->expand( $dir );
		if ( ! is_dir( $dir ) ) {
			throw new Exception( "Directory $dir does not exist." );
		}
		$ee      = escapeshellarg( $this->ee_path . '/bin/ee' );
		$command = preg_replace_callback(
			'#(^|\s)bin/ee(?=\s|$)#',
			function ( $m ) use ( $ee ) {
				return $m[1] . $ee;
			},
			$command
		);

		$this->commands[] = $this->launchWithTimeout( $command, $seconds, $dir );
	}

	/**
	 * Appends :text to :path once a `tail -f` on that file is running, to prove live following.
	 *
	 * @When I run :command with a timeout of :seconds seconds while :text is appended to the file :path once tail follows it
	 */
	public function iRunWhileTextIsAppendedOnceTailFollows( $command, $seconds, $text, $path )
	{
		$path = $this->expand( $path );
		self::assertOwnPath( $path );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "File $path must exist before it can be followed." );
		}

		// Anchored so only the tail process itself matches, never a wrapper shell's command line.
		$pattern = '^tail .*-f .*' . basename( $path );
		$writer  = sprintf(
			'( i=0; while [ $i -lt 150 ] && ! pgrep -f -- %1$s >/dev/null; do sleep 0.1; i=$((i+1)); done; sleep 1; printf "%%s\n" %2$s >> %3$s ) >/dev/null 2>&1 &',
			escapeshellarg( $pattern ),
			escapeshellarg( $this->expand( $text ) ),
			escapeshellarg( $path )
		);

		$this->commands[] = $this->launchWithTimeout( $command, $seconds, null, $writer );
	}

	/**
	 * @Then the command should still be running when the timeout expires
	 */
	public function theCommandShouldStillBeRunningWhenTheTimeoutExpires()
	{
		$result = $this->lastCommand();
		if ( 124 !== $result->return_code ) {
			throw new Exception( "Expected the command to still be running at the timeout (exit 124).\n" . self::describe( $result ) );
		}
	}

	/**
	 * @Then the command should exit with code :code
	 */
	public function theCommandShouldExitWithCode( $code )
	{
		$result = $this->lastCommand();
		if ( (int) $code !== $result->return_code ) {
			throw new Exception( "Expected exit code $code.\n" . self::describe( $result ) );
		}
	}

	/**
	 * @Then the command should exit with a non-zero code
	 */
	public function theCommandShouldExitWithANonZeroCode()
	{
		$result = $this->lastCommand();
		if ( 0 === $result->return_code || 124 === $result->return_code ) {
			throw new Exception( "Expected the command to fail on its own (non-zero exit, not a timeout).\n" . self::describe( $result ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should (not )?contain "(.*)"$/
	 */
	public function streamShouldContain( $stream, $negate, $text )
	{
		$text     = $this->expand( $text );
		$found    = false !== strpos( $this->streamOf( $stream ), $text );
		$expected = '' === $negate;
		if ( $found !== $expected ) {
			throw new Exception( sprintf( "Expected %s %sto contain '%s'.\n%s", $stream, $negate, $text, self::describe( $this->lastCommand() ) ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should contain "(.*)" exactly (\d+) times?$/
	 */
	public function streamShouldContainTimes( $stream, $text, $times )
	{
		$text  = $this->expand( $text );
		$count = substr_count( $this->streamOf( $stream ), $text );
		if ( (int) $times !== $count ) {
			throw new Exception( sprintf( "Expected %s to contain '%s' %d time(s), found %d.\n%s", $stream, $text, $times, $count, self::describe( $this->lastCommand() ) ) );
		}
	}

	/**
	 * @Then a GET request to :url with Host :host should return :code
	 */
	public function aGetRequestWithHostShouldReturn( $url, $host, $code )
	{
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->expand( $url ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Host: ' . $host ] );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		$body   = curl_exec( $ch );
		$actual = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error  = curl_error( $ch );
		curl_close( $ch );

		if ( (int) $code !== $actual ) {
			throw new Exception( "Expected HTTP $code from $url (Host: $host), got $actual. $error\n" . substr( (string) $body, 0, 1024 ) );
		}
	}

	/**
	 * @AfterScenario
	 */
	public function removeCreatedPaths( AfterScenarioScope $scope )
	{
		foreach ( array_reverse( $this->created_paths ) as $path ) {
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				@rmdir( $path );
			} elseif ( file_exists( $path ) || is_link( $path ) ) {
				@unlink( $path );
			}
		}
		$this->created_paths = [];
	}
}
