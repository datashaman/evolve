```bash
# install rvm
bash -s stable < <(curl -s https://raw.github.com/wayneeseguin/rvm/master/binscripts/rvm-installer)

# setup rvm
echo '[[ -s "$HOME/.rvm/scripts/rvm" ]] && source "$HOME/.rvm/scripts/rvm"' >> ~/.bash_profile
source ~/.bash_profile

# download & install Xcode
open https://developer.apple.com/downloads/download.action?path=Developer_Tools/xcode_4.1_for_lion/xcode_4.1_for_lion.dmg
open ~/Downloads/xcode_4.1_for_lion.dmg
open "/Volumes/Install Xcode/InstallXcodeLion.pkg"

# install ruby & rubygems
rvm install 1.9.3 # or 1.9.2 or 1.8.7

# setup an isolated environment for your project
rvm use --create 1.9.3@project-name

# install radiant
gem install radiantcms --pre --no-ri --no-rdoc # installs Radiant 1.0 RC4

# create a new radiant project
radiantcms ~/project-name
cd ~/project-name
bundle install
bundle exec rake db:bootstrap

# install optional clipped dependencies
brew install ghostscript imagemagick # or port install ...
brew install --use-gcc ffmpeg  # or port install ffmpeg

# start your new radiant app
bundle exec script/server
```
