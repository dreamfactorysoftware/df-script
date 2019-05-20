# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.8.3] - 2019-05-20
### Added
- DF-8273 Python scripting None / Null fix

## [0.8.2] - 2018-02-25
### Added
- DF-1301 Response caching using URL path and parameters as key

## [0.8.1] - 2018-01-25
### Added
- DF-1293 Added implements_access_list config option for overriding swagger def for role service access
### Fixed
- DF-1287 Fixed NodeJS (and Python) script execution for large script, making script size limit check configurable
- DF-1293 Fixed role service access components when swagger def is supplied

## [0.8.0] - 2017-12-28
### Added
- DF-1254 Allowed setting response headers in all cases
- Added package discovery
### Changed
- DF-1226 Removed deprecated v8js extension methods
- Used new df-system repository
- DF-1150 Updated copyright and support email
- Reworked service request types
- Cleanup use of checkServicePermission

## [0.7.0] - 2017-11-03
### Changed
- DF-1222 Removing write to database from script caching so that lookups are not processed prematurely
- Upgrade Swagger to OpenAPI 3.0 specification
- Fixed Node.js (and Python) scripting error due to big data set in scripts

## [0.6.2] - 2017-10-30
### Fixed
- Fix typo in error log output handling
- Fixed NodJS (and Python) scripting error due to big data set in scripts

## [0.6.1] - 2017-09-18
### Added
- DF-1177 & DF-1161 Added services for GitHub and GitLab with linking to server side scripting
- Support for setting df.scripting.default_protocol in config and DF_SCRIPTING_DEFAULT_PROTOCOL in .env

## [0.6.0] - 2017-08-17
### Changed
- Reworked API doc usage and generation
### Added
- Event script model events to utilize caching for script db queries

## [0.5.0] - 2017-07-27
- Rework Event structure to allow for non-API driven service events
- Bug fix for script handling issues

## [0.4.0] - 2017-06-05
### Changed
- Cleanup - removal of php-utils dependency
- DF-1105 Fix migration for MS SQL Server possible cascading issue

## [0.3.0] - 2017-04-21
### Changed
- Use new service config handling for database configuration
### Fixed
- DF-1054 Fixed event script execution as part of file upload
- DF-1046 Stopped NodeJS queued script execution when throwing exception
- DF-1086 Fixed script response with single quotes

## [0.2.1] - 2017-03-16
### Fixed
- Remove the $_SERVER dependency in Node and Python scripting so it can run queued

## [0.2.0] - 2017-03-03
- Major restructuring to upgrade to Laravel 5.4 and be more dynamically available

### Added
- DF-957 Added basic auth support node and python internal api call

### Fixed
- Fixed migrations with timestamp fields due to Laravel issue #11518 with some MySQL versions
- DF-915 Script tokening to authenticate internal script calls from node.js and python scripting

## [0.1.1] - 2017-01-16
### Changed
- Update dependencies for latest core
- DF-956 Adding event.setRequest() for Node.js scripting

## 0.1.0 - 2016-11-30
First official release working with the new [dreamfactory](https://github.com/dreamfactorysoftware/dreamfactory) project.

[Unreleased]: https://github.com/dreamfactorysoftware/df-script/compare/0.8.3...HEAD
[0.8.3]: https://github.com/dreamfactorysoftware/df-script/compare/0.8.2...0.8.3
[0.8.2]: https://github.com/dreamfactorysoftware/df-script/compare/0.8.1...0.8.2
[0.8.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.8.0...0.8.1
[0.8.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.6.2...0.7.0
[0.6.2]: https://github.com/dreamfactorysoftware/df-script/compare/0.6.1...0.6.2
[0.6.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.0...0.1.1
