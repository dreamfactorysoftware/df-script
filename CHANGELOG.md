# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/dreamfactorysoftware/df-script/compare/0.6.1...HEAD
[0.6.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.1...0.3.0
[0.2.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.0...0.1.1
