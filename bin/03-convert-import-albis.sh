#!/bin/bash -e
#
# dbase2mysql.sh - Migrate DBF files to MySql database
#
# Copyright (c) 2013 Junior Holowka <junior.holowka@gmail.com>
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
# THE SOFTWARE.
#
# HOWTO: ./dbase2mysql.sh - This script must be run at the same *.dbf directory



HOST="localhost"
USER="root"
PASS="pass"
BASE="database"

# check if dbf2mysql package is installed
if [ -e /usr/bin/dbf2mysql ]
then
	echo "dbf2mysql already installed!"
else
	echo "dbf2mysql being installed..."
	sudo apt-get install --yes --force-yes dbf2mysql
fi

# list files with *.dbf extension
for FILENAME in $(ls -tr *.dbf | sed 's/ /__/g')
do
 	FILENAME="$(echo $FILENAME | sed 's/__/ /g')"
	FILEXTEN="$(echo $FILENAME | sed 's/\..\{3\}$//')"

	echo -e "\033[1m===> Migrating $FILENAME to $HOST>$BASE>$FILEXTEN ... \033[0m\n"
	dbf2mysql -h $HOST -P $PASS -U $USER -d $BASE -t $FILEXTEN -c $FILENAME

done
exit 0