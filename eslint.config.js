const noJquery = require('eslint-plugin-no-jquery');

// Scoped narrowly to the jQuery DOM-injection patterns that are this
// repo's actual XSS threat model (see PR #5 on redcap_fhir_ontology_provider
// and ontology-provider-security-audit task 2.5) - not a general style
// linter.
module.exports = [
  {
    files: ['js/**/*.js'],
    languageOptions: {
      globals: {
        $: 'readonly',
        jQuery: 'readonly',
      },
    },
    plugins: {
      'no-jquery': noJquery,
    },
    rules: {
      'no-jquery/no-html': 'error',
      'no-jquery/no-append-html': 'error',
      'no-jquery/no-parse-html': 'error',
      'no-jquery/no-parse-html-literal': 'error',
    },
  },
];
