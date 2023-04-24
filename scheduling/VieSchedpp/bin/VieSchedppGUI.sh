#!/bin/sh
appname=`basename $0 | sed s,\.sh$,,`

dirname=`dirname $0`
tmp="${dirname#?}"

# uncomment the following line to download catalogs on startup:
# rm -f wget-log; wget -nd -N -b -r -A cat,good -np -P AUTO_DOWNLOAD_CATALOGS https://ivscc.gsfc.nasa.gov/IVS_AC/sked_cat

if [ "${dirname%$tmp}" != "/" ]; then
dirname=$PWD/$dirname
fi

LD_LIBRARY_PATH=$dirname
export LD_LIBRARY_PATH
"$dirname/$appname" "$@"
