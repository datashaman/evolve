For Upgrading to 1.0 see these instructions: https://gist.github.com/1540782

**If you need more specific instructions refer to the [system specific articles][install]**.

[install]: https://github.com/radiant/radiant/wiki#wiki-install

## Ruby

Many operating systems ship with a compatible version of Ruby installed by default, to check if this is the case for your system open a terminal window and run:

```bash
ruby --version
```

If you see some output that includes 1.8.7 you can skip this step. Radiant versions 1.0 and newer will also run on Ruby 1.9.2 or 1.9.3.

If you don't see any output that means you either don't have Ruby installed or wherever it is has not been added to your `PATH`. A [Google search][search] for "setting PATH" followed by the name of your operating system should get you plenty of help.

[search]: http://www.google.com/search?q=setting+PATH

### Official Ruby

The [Ruby Lang][ruby] website has instructions for installing Ruby on a variety of systems, the current Radiant release is recommended to be run on 1.8.7. Support for Ruby 1.9.2+ is included in with Radiant 1.0.

[ruby]: http://www.ruby-lang.org/en/downloads/

### Ruby Enterprise Edition

An alternative to the official Ruby package is [Ruby Enterprise Edition][ree]. The latest version of Ruby Enterprise Edition runs Radiant without issue. Refer to the [REE documentation][ree-docs] for installation instructions.

[ree]: http://www.rubyenterpriseedition.com/download.html
[ree-docs]: http://www.rubyenterpriseedition.com/documentation.html

## RubyGems

In all likelihood, if you have Ruby installed you have RubyGems installed, **although some systems do ship them separately**. To determine if you have RubyGems installed go to a terminal window and run:

```bash
gem --version
```

If you see some output that includes a 1.3.7 or higher version number you can skip this step.

If you see some output but the version number is lower than 1.3.7 then you need to upgrade your RubyGems installation. Refer to the [RubyGems download page][gems] for instructions.

If you don't see any output you'll need to install the latest version of RubyGems. Refer to the [RubyGems download page][gems] for instructions.

[gems]: https://rubygems.org/pages/download

## Ruby on Rails

Radiant versions less than 1.x vendor Rails so you do not need to install it. Versions 1.x and greater will install the appropriate version of Rails as part of the `gem install radiant` process.

## The Radiant Gem

Once you have Ruby and RubyGems installed the Radiant gem can be installed by running:

```bash
gem install radiant
```

Depending on your system you may need to install the gem with sudo. `sudo gem install radiant`

## Creating a New Project

Once you have the Radiant gem installed you can create a new project with the `radiant` command (which is similar to the `rails` command, but Radiant specific). Run `radiant --help` for details.

```bash
# Radiant 1.x
radiant ~/project_name # uses sqlite by default
cd ~/project_name
bundle install
bundle exec rake db:bootstrap
bundle exec script/server

# Radiant 0.x
radiant -d sqlite3 ~/project_name
cd ~/project_name
rake db:bootstrap
script/server
```
