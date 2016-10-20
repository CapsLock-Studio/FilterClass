if [ -z ${TRAVIS} ]; then
    exit
fi

symbolic=/usr/local/bin/analyze

if [ ! -f symbolic ]; then
    rm -rf $symbolic
fi

ln -s $(pwd)/bin/analyze $symbolic
