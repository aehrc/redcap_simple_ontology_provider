# Changelog

## [1.0.0](https://github.com/aehrc/redcap_simple_ontology_provider/compare/v0.5.0...v1.0.0) (2026-09-10)


### ⚠ BREAKING CHANGES

* add priority-codes category setting ([#21](https://github.com/aehrc/redcap_simple_ontology_provider/issues/21))
* php-version-min is 8.0.0 (raised from 5.4.0 in PR #12, "chore: upgrade to EM framework version 16"). Sites running PHP older than 8.0 cannot install or enable this module version.

### Features

* add ontology cache refresh, with a system-level enable/disable switch ([#16](https://github.com/aehrc/redcap_simple_ontology_provider/issues/16)) ([57e1d28](https://github.com/aehrc/redcap_simple_ontology_provider/commit/57e1d28edbd7a286c88e29972c700fafb7f1521c))
* add priority-codes category setting ([#21](https://github.com/aehrc/redcap_simple_ontology_provider/issues/21)) ([25c9a04](https://github.com/aehrc/redcap_simple_ontology_provider/commit/25c9a0479818bd1bf5bbe6d3395ab57b82c49227))


### Bug Fixes

* document the PHP 8.0 minimum and flag it as the breaking change it was ([#17](https://github.com/aehrc/redcap_simple_ontology_provider/issues/17)) ([3bd4d63](https://github.com/aehrc/redcap_simple_ontology_provider/commit/3bd4d63416bdccf92d1f079f22ee4c7bedf7f0d0))
* reject non-scalar code/display in JSON-format category Values ([#23](https://github.com/aehrc/redcap_simple_ontology_provider/issues/23)) ([9a734b7](https://github.com/aehrc/redcap_simple_ontology_provider/commit/9a734b7c3fec39dc3c81794cd11865492c3073b6))
* repair @HIDECHOICE, add @SIMPLE-ONTOLOGY-HIDECHOICE and a return-all flag ([#19](https://github.com/aehrc/redcap_simple_ontology_provider/issues/19)) ([727fedc](https://github.com/aehrc/redcap_simple_ontology_provider/commit/727fedc4c81aef0be19a192a9290dbb768132a8a))

## [0.5] - 2021-05-04
- Add support for synonyms (alternative search terms for a code)
- Add an active flag to exclude inactive entries from search results
- Add `@HIDECHOICE` support
- Add a Spanish translation of the README

## [0.4] - 2020-07-31
- Add an option to choose the old (whole-string) search behaviour instead of the word-based search introduced in 0.3

## [0.3.2] - 2020-02-10
- Add Spanish language support (contributed by Dr Daniel Hinostroza)
- Fix a spelling mistake in configuration options

## [0.3.1] - 2020-02-03
- Fix a null `$project_id` handling issue in a hook

## [0.3] - 2019-04-02
- Add word-based search: split the search string into words and search each individually, sorting by word-match count then position

## [0.2] - 2019-03-21
- Add accent handling in search text
- Add an option to return a predefined value instead of an empty result set

## [0.1] - 2018-11-22
- Initial release
