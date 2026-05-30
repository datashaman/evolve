```bash
# curl is needed to install rvm
sudo apt-get install -y curl

# install rvm
bash -s stable < <(curl -s https://raw.github.com/wayneeseguin/rvm/master/binscripts/rvm-installer)

# setup rvm
echo '[[ -s "$HOME/.rvm/scripts/rvm" ]] && source "$HOME/.rvm/scripts/rvm"' >> ~/.bash_profile
source ~/.bash_profile

# install rvm dependencies
sudo apt-get install -y build-essential openssl libreadline6 libreadline6-dev \
git zlib1g zlib1g-dev libssl-dev libyaml-dev libsqlite3-0 libsqlite3-dev sqlite3 \
libxml2-dev libxslt-dev autoconf libc6-dev ncurses-dev automake libtool bison subversion

# install ruby & rubygems
rvm install 1.9.3 # or 1.9.2 or 1.8.7

# setup an isolated environment for your project
rvm use --create 1.9.3@project-name

# install optional clipped dependencies for radiant
sudo apt-get install -y ghostscript imagemagick ffmpeg

# install radiant
gem install radiant --pre --no-ri --no-rdoc # installs Radiant 1.0 RC4

# create a new radiant project
radiant ~/project-name
cd ~/project-name
echo 'gem "therubyracer", "~> 0.9"' >> Gemfile # or sudo apt-get install -y nodejs
bundle install
bundle exec rake db:bootstrap

# start your new radiant app
bundle exec script/server
```
