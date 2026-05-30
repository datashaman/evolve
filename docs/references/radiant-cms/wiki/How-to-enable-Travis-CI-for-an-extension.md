[Travis CI][travis] is a flexible and easy to use continuous integration server; see the [Getting Started page][travis-getting-started] for more details. The flexibility means that there are any number of ways to setup and test your extensions. Here is one way.

First you need to add a `.travis.yml` file to the root of your repository.

```yaml
rvm:
  - 1.8.7
  - 1.9.2
  - 1.9.3

before_script: "./spec/ci/before_script"

script: "./spec/ci/script"

env:
  - RADIANT_VERSION=0.9.1 DB=mysql
  - RADIANT_VERSION=0.9.1 DB=postgres
  - RADIANT_VERSION=1.0.0 DB=mysql
  - RADIANT_VERSION=1.0.0 DB=postgres
  - RADIANT_VERSION=master DB=mysql
  - RADIANT_VERSION=master DB=postgres

# older versions of radiant do not run on ruby 1.9.x
# use matrix exclusions to prevent them running
matrix:
  exclude:
    - rvm: 1.9.2
      env: RADIANT_VERSION=0.9.1 DB=mysql
    - rvm: 1.9.2
      env: RADIANT_VERSION=0.9.1 DB=postgres
    - rvm: 1.9.3
      env: RADIANT_VERSION=0.9.1 DB=mysql
    - rvm: 1.9.3
      env: RADIANT_VERSION=0.9.1 DB=postgres

notifications:
  recipients:
    - you@example.com
```

The `rvm` array shows the Ruby versions you want to test against; since Radiant 1.0 supports the three latest Ruby releases your extension should too. Likewise the `env` array list Radiant version/database combinations to test against; the Radiant version can be any [tag][tags] or [branch][branches] name.

Next up we need to create the `before_script` referenced above. The `before_script` is run before your tests and sets up a complete Radiant environment within which your tests will be run.

```shell
cd ~
git clone git://github.com/radiant/radiant.git
cd ~/radiant
if [[ $RADIANT_VERSION != "master" ]]
then
  git checkout -b $RADIANT_VERSION $RADIANT_VERSION
fi
cp -r ~/builds/*/YOUR_REPOSITORY_NAME vendor/extensions/YOUR_EXTENSION_NAME
bundle install

case $DB in
  "mysql" )
    mysql -e 'create database radiant_test;'
    cp spec/ci/database.mysql.yml config/database.yml;;
  "postgres" )
    psql -c 'create database radiant_test;' -U postgres
    cp spec/ci/database.postgresql.yml config/database.yml;;
esac

bundle exec rake db:migrate
bundle exec rake db:migrate:extensions
```

Replace `YOUR_REPOSITORY_NAME` and `YOUR_EXTENSION_NAME` in the `before_script` with the actual name of your repo and extension; e.g. `radiant-sheets-extension` and `sheets` respectively. **Don't forget to make this script executable before committing it to your repository**.

Finally we need to create the `script` file. This is the script that actually executes your tests.

```shell
cd ~/radiant
bundle exec rake spec:extensions EXT=YOUR_EXTENSION_NAME
```

Again you need to replace `YOUR_EXTENSION_NAME` with the real thing and **make the script executable**.

All that's left to do is login to [Travis][travis], enable the commit-hook for you repository and push. Head over to <http://travis-ci.org/> and click the "Sign in with GitHub" link. Once you've authorized through GitHub visit your profile page on Travis, find the extension to enable and flick the switch to on.

Now you're ready to commit and push the `.travis.yml`, `before_script` and `script` files and let Travis handle the rest. If you want to know more about all the options you have with Travis the [documentation][docs] is very good. You can see an example of this setup in action on the [Sheets][sheets-repo] extension or check out what it looks like to have your specs run by Travis by visiting the Travis page for [Sheets][sheets-ci]. Once setup Travis will run your tests after every commit unless you add `[ci skip]` to the commit message (which is good practice when pushing documentation or other commits that don't affect functionality).

[travis]: http://travis-ci.org/
[travis-getting-started]: http://about.travis-ci.org/docs/user/getting-started/
[tags]: https://github.com/radiant/radiant/tags
[docs]: http://about.travis-ci.org/docs/
[sheets-repo]: https://github.com/radiant/radiant-sheets-extension
[sheets-ci]: http://travis-ci.org/radiant/radiant-sheets-extension
[branches]: https://github.com/radiant/radiant/branches