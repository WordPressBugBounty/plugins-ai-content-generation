/**
 * Babel config — a copy of @wordpress/babel-preset-default with ONE change: the JSX transform runs
 * in `classic` mode instead of `automatic`.
 *
 * Why: the automatic runtime makes every bundle import `react/jsx-runtime`, which
 * @wordpress/dependency-extraction-webpack-plugin turns into a `react-jsx-runtime` script
 * dependency. WordPress only registers that handle from 6.6 onwards, so on anything older the
 * bundle never loads and the screen renders blank — with no error a site owner could act on.
 * Telemetry (2026-08-20) put 990 of 2,293 active installs, 43%, below 6.6.
 *
 * Classic mode emits `createElement(...)` instead, and @wordpress/babel-plugin-import-jsx-pragma
 * adds the matching `import { createElement } from '@wordpress/element'`. That resolves to the
 * `wp-element` handle, which has shipped since WordPress 5.0.
 *
 * wp-scripts only falls back to its own Babel options when a project config is absent
 * (`hasBabelConfig()` in @wordpress/scripts/config/webpack.config.js), so this file replaces it
 * wholesale — which is why the rest of the preset is reproduced here rather than extended. Keep it
 * in step with node_modules/@wordpress/babel-preset-default/index.js when that package is upgraded.
 */
const browserslist = require( 'browserslist' );

module.exports = ( api ) => {
	const isTestEnv = api.env() === 'test';

	// The upstream preset reads these off the Babel caller so the WordPress monorepo can build its
	// own packages. Nothing here is a WP_BUILD_* caller, so the branches that depend on it are the
	// plain-site defaults; they are inlined rather than carried.
	api.cache.using( () => process.env.NODE_ENV );

	const presetEnvOptions = {
		bugfixes: true,
	};

	if ( isTestEnv ) {
		presetEnvOptions.targets = { node: 'current' };
	} else {
		presetEnvOptions.modules = false;
		const localBrowserslistConfig = browserslist.findConfig( '.' ) || {};
		presetEnvOptions.targets = {
			browsers:
				localBrowserslistConfig.defaults ||
				require( '@wordpress/browserslist-config' ),
		};
	}

	return {
		presets: [
			[ require.resolve( '@babel/preset-env' ), presetEnvOptions ],
			require.resolve( '@babel/preset-typescript' ),
		],
		plugins: [
			require.resolve( '@babel/plugin-syntax-import-attributes' ),
			require.resolve( '@wordpress/warning/babel-plugin' ),
			// Must run before the JSX transform so `createElement` / `Fragment` are in scope by the
			// time classic-mode JSX references them.
			[
				require.resolve(
					'@wordpress/babel-plugin-import-jsx-pragma'
				),
				{
					scopeVariable: 'createElement',
					scopeVariableFrag: 'Fragment',
					source: '@wordpress/element',
					isDefault: false,
				},
			],
			[
				require.resolve( '@babel/plugin-transform-react-jsx' ),
				{
					runtime: 'classic',
					pragma: 'createElement',
					pragmaFrag: 'Fragment',
				},
			],
			...( isTestEnv
				? []
				: [
						[
							require.resolve(
								'@babel/plugin-transform-runtime'
							),
							{ helpers: true, useESModules: false },
						],
				  ] ),
		],
	};
};
