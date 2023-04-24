# VieSched++

VLBI scheduling software

written by Matthias Schartner

contact: matthias.schartner@geo.tuwien.ac.at

----
# INSTALLATION

This are the *prebuilt binaries for Ubuntu*.

Simply extract this archive into a directory like `~/VieSchedpp`.

If you want to have access to the source code clone our git repositories.

----
# START

If you want to start the graphical user interface run the bash file located in `bin/VieSchedppGUI.sh`.

If you manually want to start the scheduler execute `bin/VieSchedpp` with the path to an existing `VieSchedpp.xml` file as an input argument.

---- 
# IMPORTANT NOTES

On startup, VieSched++ should automatically **download the newest catalogs**. 
To download files from https, you need a cryptogrphy library such as OpenSSL. 
The OpenSSL package is **NOT** part of this software package due to legal restrictions in some contries.
If the OpenSSL library installed on your OS is not compatible with the OpenSSL library needed by VieSched++ the download will fail and you will get error messages such as:

    qt.network.ssl: QSslSocket: cannot call unresolved function SSLv23_client_method
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_CTX_new
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_library_init
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    qt.network.ssl: QSslSocket: cannot call unresolved function SSLv23_client_method
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_CTX_new
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_library_init
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    We got a connection error when networkLayerState is Unknown
    qt.network.ssl: QSslSocket: cannot call unresolved function SSLv23_client_method
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_CTX_new
    qt.network.ssl: QSslSocket: cannot call unresolved function SSL_library_init
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    qt.network.ssl: QSslSocket: cannot call unresolved function ERR_get_error
    ...

 or

	qt.network.ssl: QSslSocket::connectToHostEncrypted: TLS initialization failed

However, it is **highly recommended** to download the latest catalogs on startup. 
There are two ways to achieve this:

- install the OpenSSL library manually: `sudo apt-get install libssl1.0-dev`
- download the catalogs using the `VieSchedppGUI.sh` script. You have to uncomment one line in the  `VieSchedppGUI.sh` file to start the download in a background process on startup

# TROUBLESHOOTING

Sometimes it may cause problems if the path to the installation directory contains whitespaces.

The installation was tested on Ubuntu 18.04 but will probably work with other versions as well

If you have problem with VieSched++ contact matthias.schartner@geo.tuwien.ac.at
