<?php namespace Bkwld\Decoy\Auth;

// Deps
use Config;
use DecoyURL;
use HTML;
use Illuminate\Auth\AuthManager;

/**
 * Authentication using Eloquent queries. This is the Decoy default
 */
class Eloquent implements AuthInterface {

	/**
	 * @var Illuminate\Auth\AuthManager 
	 */
	private $auth;

	/**
	 * Dependency injection
	 * 
	 */
	public function __construct(AuthManager $auth) {
		$this->auth = $auth;
	}

	/**
	 * ---------------------------------------------------------------------------
	 * Check perimssions
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * Boolean for whether the user is logged in and an admin
	 * 
	 * @return boolean
	 */
	public function check() {
		return $this->auth->check();
	}

	/**
	 * Get a list of all roles
	 *
	 * @return array 
	 */
	public function roles() {
		$roles = Config::has('decoy::site.permissions');
		if (!is_array($roles)) return [];
		return array_keys($roles);
	}

	/**
	 * Check if a user is in a specific role or return the list of all roles
	 * 
	 * @param  string $role
	 * @return boolean | array
	 */
	public function isRole($role) {
		$this->user()->role == $role;
	}

	/**
	 * Check if the user has permission to do something
	 * 
	 * @param string $action ex: destroy
	 * @param string $controller 
	 *        - controller instance
	 *        - controller name (Admin\ArticlesController)
	 *        - URL (/admin/articles)
	 *        - slug (articles)
	 * @return boolean
	 */
	public function can($action, $controller) {

		// They must be logged in
		if (!$this->check()) return false;

		// If no permissions have been defined, do nothing.  Btw, only supporting "cant" for now.
		if (!Config::has('decoy::site.permissions')) return true;

		// Convert controller instance to it's name
		if (is_object($controller)) $controller = get_class($controller);

		// Get the slug version of the controller.  Test if a URL was passed first
		// and, if not, treat it like a full controller name.  URLs are used in the nav.
		// Also, an already slugified controller name will work fine too.
		if (preg_match('#/'.Config::get('decoy::core.dir').'/([^/]+)#', $controller, $matches)) {
			$controller = $matches[1];
		} else $controller = DecoyURL::slugController($controller);

		// Loop through the users roles
		foreach($this->roles() as $role) {
			$actions = Config::get('decoy::site.permissions.'.$role.'.cant');

			 // If no permissions are defined in can't, they are good to go
			if (empty($actions)) continue;

			// If the action is listed as "can't" then immediately deny.  Also check for
			// "manage" which means they can't do ANYTHING
			if (in_array($action.'.'.$controller, $actions) 
				|| in_array('manage.'.$controller, $actions)) return false;
		}

		// I guess we're good to go
		return true;
	}

	/**
	 * ---------------------------------------------------------------------------
	 * Methods for inspecting properties of the user
	 * ---------------------------------------------------------------------------
	 */
	
	/**
	 * Return the authed Admin model
	 *
	 * @return Bkwld\Decoy\Models\Admin
	 */
	public function user() {
		return $this->auth->user();
	}

	/**
	 * Boolean as to whether the user has developer entitlements
	 * 
	 * @return boolean
	 */
	public function developer() {
		$this->user()->role == 'developer';
	}

	/**
	 * Avatar photo for the header
	 * 
	 * @return string
	 */
	public function userPhoto() {
		return $this->user()->image ?: HTML::gravatar($this->user()->email);
	}

	/**
	 * Name to display in the header for the user
	 * 
	 * @return string
	 */
	public function userName() {
		return $this->user()->first_name;
	}

	/**
	 * URL to the user's profile page in the admin
	 * 
	 * @return string
	 */
	public function userUrl() {
		return DecoyURL::action('Bkwld\Decoy\Controllers\Admins@edit', $this->auth->id());
	}
	
	/**
	 * ---------------------------------------------------------------------------
	 * URLs & Routes related to authing
	 * ---------------------------------------------------------------------------
	 */

	/**
	 * URL that contains the login page
	 * 
	 * @return string
	 */
	public function loginAction() {
		return 'Bkwld\Decoy\Controllers\Account@login';
	}
	
	/**
	 * URL to process logging out
	 * 
	 * @return string
	 */
	public function logoutUrl() {
		return route('decoy::account@logout');
	}
	
	/**
	 * URL to redirect to if a user doesn't have permission for the page
	 * 
	 * @return string
	 */
	public function deniedUrl() {
		return action($this->loginAction());
	}

}