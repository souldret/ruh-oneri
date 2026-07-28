<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem;

/**
 * Class Loader
 *
 * Maintains collections of hooks and registers them with WordPress.
 */
final class Loader {

	/**
	 * Actions to register.
	 *
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	private array $actions = array();

	/**
	 * Filters to register.
	 *
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	private array $filters = array();

	/**
	 * Add an action.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $hook          WordPress action name.
	 * @param object|string $component     Object or class with the callback.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Priority.
	 * @param int           $accepted_args Number of accepted arguments.
	 */
	public function add_action(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $hook          WordPress filter name.
	 * @param object|string $component     Object or class with the callback.
	 * @param string        $callback      Method name.
	 * @param int           $priority      Priority.
	 * @param int           $accepted_args Number of accepted arguments.
	 */
	public function add_filter(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility to build a hook collection entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array         $hooks         Existing hooks.
	 * @param string        $hook          Hook name.
	 * @param object|string $component     Component.
	 * @param string        $callback      Callback method.
	 * @param int           $priority      Priority.
	 * @param int           $accepted_args Accepted args.
	 * @return array
	 */
	private function add(
		array $hooks,
		string $hook,
		object|string $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register all collected hooks with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
