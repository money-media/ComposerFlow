# Composer Flow 

ComposerFlow is designed for teams using [Composer](https://getcomposer.org/) and [git-flow](https://github.com/nvie/gitflow).
It will (eventually) take a production release of a Composer (eventually node etc.) package
and ensure that changes from the release are merged back into develop and master.

## Commands

#### `status`

Gets the difference between branches – i.e. are these things merged? How many commits does each have that the other lacks?
<img src="http://i.imgur.com/CVmNuaJ.png">

#### `merge`

Merge a set of project branches.
