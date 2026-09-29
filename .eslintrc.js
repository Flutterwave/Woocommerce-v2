module.exports = {
	extends: [ 'plugin:@woocommerce/eslint-plugin/recommended' ],
	rules: {
		// You can use the 'rules' key to override specific settings in the WooCommerce plugin
		'@wordpress/i18n-translator-comments': 'warn',
		'@wordpress/valid-sprintf': 'warn',
		'jsdoc/check-tag-names': [
			'error',
			{ definedTags: [ 'jest-environment' ] },
		],
	},
	settings: {
		// List of modules that are externals in our webpack config.
		// This helps the `import/no-extraneous-dependencies` and
		//`import/no-unresolved` rules account for them.
		'import/core-modules': [
			'@woocommerce/blocks-registry',
			'@woocommerce/settings',
			'@wordpress/api-fetch',
			'@wordpress/i18n',
			'@wordpress/is-shallow-equal',
			'@wordpress/element',
		],
	},
};