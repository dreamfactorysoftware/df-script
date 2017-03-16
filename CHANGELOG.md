# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.1...HEAD
[0.2.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dreamfactorysoftware/df-script/compare/0.1.0...0.1.1
