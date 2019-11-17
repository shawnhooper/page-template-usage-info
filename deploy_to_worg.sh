#! /bin/bash
#
# Script to deploy from Github to WordPress.org Plugin Repository
# A modification of Dean Clatworthy's deploy script as found here: https://github.com/deanc/wordpress-plugin-git-svn
# The difference is that this script lives in the plugin's git repo & doesn't require an existing SVN repo.

#prompt for plugin slug
echo -e "Plugin Slug: \c"
read PLUGINSLUG

# main config
CURRENTDIR=`pwd`
CURRENTDIR="$CURRENTDIR/"
MAINFILE="$PLUGINSLUG.php" # this should be the name of your main php file in the wordpress plugin

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG/" # Remote SVN repo on wordpress.org, with no trailing slash

echo -e "What is your SVN username?"
read SVNUSER; # get the SVN username


# Let's begin...
echo ".........................................."
echo 
echo "Preparing to deploy wordpress plugin"
echo 
echo ".........................................."
echo 

# Check version in readme.txt
NEWVERSION1=`grep "^Stable tag" "$GITPATH"/readme.txt | awk -F' ' '{print $3}'`
echo "readme version: $NEWVERSION1"

cd "$GITPATH"
echo -e "Enter a commit message for this new version: \c"
read COMMITMSG
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "v$NEWVERSION1" -m "$COMMITMSG"

echo "Pushing latest commit to origin, with tags"
git push origin master
git push origin master --tags

echo 
echo "Creating local copy of SVN repo ..."
svn co $SVNURL $SVNPATH

echo "Exporting the HEAD of master from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

echo "Ignoring github specific & deployment script"
svn propset svn:ignore "deploy.sh
README.md
.git
.gitignore" "$SVNPATH/trunk/"

echo "Moving assets-wp-repo"
mkdir $SVNPATH/assets/
mv $SVNPATH/trunk/assets-wp-repo/* $SVNPATH/assets/
svn add $SVNPATH/assets/
svn delete $SVNPATH/trunk/assets-wp-repo

echo "Changing directory to SVN"
cd $SVNPATH/trunk/
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
echo "committing to trunk"
svn commit --username=$SVNUSER -m "$COMMITMSG"

echo "Updating WP plugin repo assets & committing"
cd $SVNPATH/assets/
svn commit --username=$SVNUSER -m "Updating wp-repo-assets"

echo "Creating new SVN tag & committing it"
cd $SVNPATH
svn copy trunk/ tags/$NEWVERSION1/
cd $SVNPATH/tags/$NEWVERSION1
svn commit --username=$SVNUSER -m "Tagging version $NEWVERSION1"

echo "Removing temporary directory $SVNPATH"
rm -fr $SVNPATH/

echo "*** FIN ***"
