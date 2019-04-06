<?php
header( 'content-type: text/html; charset:utf-8' );
session_start();
error_reporting(0);

/*

2019-04-06 0.5.2
- When setting play mode, selected song now always starts at index 0 when shuffle is on
- Equalised URI encoding of cookies
- In playlist mode, adding/removing a song to the playlist now also updates the active songlist
- Added password protection
- Added buttons to reorder files in playlist
- Added theme parameters

2019-02-09 0.4.5
- Removed background-repeat and accent colour from albumart

2019-02-08 0.4.4
- Prevented directories above root from being accessed
- Escaped apostrophe in add to/remove from playlist links
- Added "Clear playlist" button

2019-01-29 0.4.1
- Implemented playlist
- Switched from window.onload to DOMContentLoaded event listener

2019-01-27 0.3.2
- Implemented shuffle
- Updated button placement
- Made displayed browse directory constant

2019-01-21 0.2.4
- Updated ignored file list
- Added empty directory message
- Enabled automatic advance to next song in directory
- Added playback error message
- Fixed session and cookie handling
- Added buttons to layout
- Fixed sorting bug

2019-01-20 0.1.5
- Made design responsive
- Made player sticky on top
- Made player width customisable
- Highlighted current song in directory index
- Added background image

2019-01-19 0.1.0
- Persistent music player for single files allowing free simultaneous browsing


T O D O 
- swipe left/right for next/previous


*/

$allowedExtensions = array( 'mp3', 'flac', 'wav', 'ogg' );
$excluded = array( '.', '..', '.git', '.htaccess', '.htpasswd', 'cgi-bin', 'getid3', 'logs', 'usage');

$width = '40%';

$backgroundimg = 'bg.jpg';
$background = '#222';
$accentfg = '#000';
$accentbg = '#fc0';
$menubg = '#eee';
$menushadow = '#ddd';
$gradient1 = '#1a1a1a';
$gradient2 = '#444';
$filebuttonfg = '#bbb';

$backgroundimg = 'bg_dark.jpg';
$background = '#333';
$accentfg = '#000';
$accentbg = '#fff';
$menubg = '#ddd';
$menushadow = '#ccc';
$gradient1 = '#1a1a1a';
$gradient2 = '#444';
$filebuttonfg = '#bbb';

$backgroundimg = 'bg_forest.jpg';
$background = '#556555';
$accentfg = '#000';
$accentbg = '#c4dd2a';
$menubg = '#eee';
$menushadow = '#ddd';
$gradient1 = '#1a1a1a';
$gradient2 = '#444';
$filebuttonfg = '#bbb';




$password = '12345';


if( isset( $_POST['password'] ) ) {
    if ( htmlspecialchars($password) == htmlspecialchars( $_POST['password'] ) ) {
        $_SESSION['authenticated'] = 'yes';
        loadPage();
    }
} elseif( isset( $_GET['play'] ) ) {
    ### playing the indicated song
    $song = sanitizeGet( $_GET['play'] );

    if ( is_file( $song ) ) {
        # obtaining song info
        $songInfo = getsonginfo( $song );

        # getting list of songs in this directory
        $dirsonglist = getDirContents( dirname( $song ) );
        foreach ($dirsonglist['files'] as &$file) {
            $file = dirname( $song ) . '/' . $file;
        } unset($file);

        # setting cookies
        setcookie( 'nm_nowplaying', $song, strtotime( '+1 day' ) );
        setcookie( 'nm_songs_currentsongdir', json_encode( $dirsonglist['files'] ), strtotime ( '+1 week' ) );

        # updating active song list if empty
        if ( !isset ( $_COOKIE['nm_songs_active'] ) ) {
            setcookie( 'nm_songs_active', json_encode( $dirsonglist['files'] ), strtotime ( '+1 week' ) );
            $activesonglist = $dirsonglist['files'];
        } else {
            $activesonglist = json_decode( $_COOKIE['nm_songs_active'], true );
        }
        
        # updating active song index
        setcookie( 'nm_songs_active_idx', array_search( $song, $activesonglist ), strtotime ( '+1 week' ) );
        
        # no error message
        $error = '';
    } else {
        # defaulting to root directory and displaying error message
        $songInfo = array();
        $error = "Could not find file {$song}.";
        $song = '';
    }

    loadPage( $song, $error, $songInfo );
} elseif( isset( $_GET['which'] ) )  {
    ### responding to AJAX request for next/previous song in songlist
    $which = sanitizeGet( $_GET['which'] );

    if ( isset( $_COOKIE['nm_songs_active'] ) && isset( $_COOKIE['nm_songs_active_idx'] ) ) {
        $songlist = json_decode( $_COOKIE['nm_songs_active'], true );
        $currentIndex = $_COOKIE['nm_songs_active_idx'];

        if ( $which === 'next' && isset( $songlist[$currentIndex + 1] ) ) {
            echo $songlist[$currentIndex + 1];
        } elseif ( $which === 'previous' && isset( $songlist[$currentIndex - 1] ) ) {
            echo $songlist[$currentIndex - 1];
        }
    }
} elseif( isset( $_GET['dir'] ) )  {
    ### responding to AJAX request for directory contents
    
    if ( !isset ( $_SESSION['authenticated'] ) ) {
        # show "Password required [             ]"
        echo <<<PASSWORDREQUEST
<div id="header"><div id="passwordrequest">
    Password required
    <form action="." method="post">
        <input type="password" name="password" id="passwordinput" />
        <input type="submit" />
    </form>
</div></div>';
PASSWORDREQUEST;
        die();
    }
    
    $basedir = sanitizeGet( $_GET['dir'] );

    if ( is_dir( $basedir ) && !in_array( '..', explode( '/', $basedir ) ) ) {
        # setting currentbrowsedir cookie
        setcookie( 'nm_currentbrowsedir', $basedir, strtotime( '+1 day' ) );

        # listing directory contents
        $dirContents = getDirContents( $basedir );

        # returning header
        echo '<div id="header">';
        renderButtons();
        echo '<div id="breadcrumbs">';
        $breadcrumbs = explode( '/', $basedir );
        for ( $i = 0; $i != sizeof( $breadcrumbs ); $i++ ) {
            $title = $breadcrumbs[$i] == '.'  ? 'Root'  : $breadcrumbs[$i];

            if ($i == sizeof($breadcrumbs) - 1) {
                # current directory
                echo "<span id=\"breadcrumbactive\">{$title}</span>";
            } else {
                # previous directories with link
                $link = implode( '/', array_slice( $breadcrumbs, 0, $i+1 ) );
                echo "<span class=\"breadcrumb\" onclick=\"goToDir('{$link}');\">{$title}</span><span class=\"separator\">/</span>";
            }
        }
        echo '</div>';
        echo '</div>';

        if ( empty( $dirContents['dirs'] ) && empty( $dirContents['files'] ) ) {
            # nothing to show
            echo '<div id="filelist" class="list"><div>This directory is empty.</div></div>';
        } else {
            # returning directory list
            if ( !empty( $dirContents['dirs'] ) ) {
                echo '<div id="dirlist" class="list">';
                foreach ( $dirContents['dirs'] as $dir ) {
                    $link = $basedir . '/' . $dir;
                    echo "<div class=\"dir\" onclick=\"goToDir('{$link}');\">{$dir}</div>";
                } unset( $dir );
                echo '</div>';
            }

            # returning file list
            if ( !empty( $dirContents['files'] ) ) {
                echo '<div id="filelist" class="list">';
                foreach ( $dirContents['files'] as $file ) {
                    $link = $basedir . '/' . $file;
                    $jslink = str_replace( "'", "\'", $link );
                    $nowplaying = ( isset( $_COOKIE['nm_nowplaying'] ) && $_COOKIE['nm_nowplaying'] == $link ) ? ' nowplaying' : '';
                    echo "<div class=\"file{$nowplaying}\"><a href=\"?play={$link}\" onclick=\"setPlayMode('browse', '{$jslink}');\">&#x25ba; {$file}</a><div class=\"filebutton\" onclick=\"addToPlaylist('{$jslink}');\" title=\"Add to playlist\">+</div></div>";
                } unset( $file );
                echo '</div>';
            }
        }
    }
} elseif( isset( $_GET['playlist'] ) )  {
    ### responding to AJAX request for playlist contents

    if ( isset( $_COOKIE['nm_songs_playlist'] ) ) {
        $playlist = json_decode( $_COOKIE['nm_songs_playlist'], true );
    }

    # returning header
    echo '<div id="header">';
    renderButtons();
    echo '<div id="playlisttitle">Playlist</div>';
    echo '</div>';

    if ( empty( $playlist ) ) {
        # nothing to show
        echo '<div id="filelist" class="list"><div>This playlist is empty.</div></div>';
    } else {
        echo '<div id="filelist" class="list">';
        foreach ( $playlist as $link ) {
            $song = basename( $link );
            $nowplaying = ( isset( $_COOKIE['nm_nowplaying'] ) && $_COOKIE['nm_nowplaying'] == $link ) ? ' nowplaying' : '';
            $jslink = str_replace( "'", "\'", $link );
            echo "<div class=\"file{$nowplaying}\"><a href=\"?play={$link}\" onclick=\"setPlayMode('playlist', '{$jslink}');\">&#x25ba; {$song}</a><div class=\"filebutton\" onclick=\"moveInPlaylist('{$jslink}', -1);\"title=\"Move up\">&#x2191</div><div class=\"filebutton\" onclick=\"moveInPlaylist('{$jslink}', 1);\"title=\"Move down\">&#x2193</div><div class=\"filebutton\" onclick=\"removeFromPlaylist('{$jslink}');\" title=\"Remove from playlist\">&#x00d7</div></div>";
        } unset( $file );
        echo '</div>';
    }
} else {
    ### rendering default site
    loadPage();
}


function renderButtons() {
    # toggling active class for active buttons
    $viewMode = ( isset( $_COOKIE['nm_viewmode'] ) && $_COOKIE['nm_viewmode'] == 'playlist' ) ? 'playlist' : 'browse';
    $playlistActive = ( $viewMode == 'playlist' ) ? ' active' : '';
    $browseActive = ( $viewMode == 'browse' ) ? ' active' : '';
    $shuffleActive = ( isset( $_COOKIE['nm_shuffle'] ) && $_COOKIE['nm_shuffle'] == 'on' ) ? ' active' : '';

    # setting browse directory when browse mode is activated
    if ( isset( $_COOKIE['nm_currentbrowsedir'] ) ) { $dir = $_COOKIE['nm_currentbrowsedir']; }
    elseif ( isset( $_COOKIE['nm_currentsongdir'] ) ) { $dir = $_COOKIE['nm_currentsongdir']; }
    else { $dir = '.'; }
    
    # rendering playlist buttons when in playlist mode
    if ( $viewMode == 'playlist' ) {
        $playlistButtons = <<<PLBUTTONS
        <div class="button" onclick="clearPlaylist();"><span>Clear</span></div>
        <div class="separator"></div>
PLBUTTONS;
    } else {
        $playlistButtons = '';
    }

    # rendering general buttons
    echo <<<BUTTONS
    <div class="buttons">
        {$playlistButtons}
        <div class="button{$shuffleActive}" id="shufflebutton" onclick="toggleShuffle();"><span>Shuffle</span></div>
        <div class="separator"></div>
        <div class="button border{$browseActive}" onclick="goToDir('{$dir}');"><span>Browse</span></div>
        <div class="button{$playlistActive}" onclick="goToPlaylist('default')"><span>Playlist</span></div>
    </div>
BUTTONS;
}


function getDirContents( $dir ) {
    global $excluded, $allowedExtensions;

    # browsing given directory
    if ( $dh = opendir( $dir ) ) {
        while ( $itemName = readdir( $dh ) ) {
            # ignoring certain files
            if ( !in_array( $itemName, $excluded ) ) {
                if ( is_file( $dir . '/' . $itemName ) ) {
                    # found a file: adding allowed files to file array
                    $info = pathinfo( $itemName );
                    if ( isset( $info['extension'] ) && in_array( strtolower( $info['extension'] ), $allowedExtensions ) ) {
                        $fileList[] = $info['filename'] . '.' . $info['extension'];
                    }
                } elseif ( is_dir( $dir . '/' . $itemName ) ) {
                    # found a directory: adding to directory array
                    $dirList[] = $itemName;
                }
            }
        }
        closedir($dh);
    }

    usort( $dirList, 'compareName' ) ;
    usort( $fileList, 'compareName' );

    return array('dirs' => $dirList, 'files' => $fileList);
}


function getSongInfo( $song ) {
    ### if available, using getID3 to extract song info

    if ( file_exists( './getid3/getid3.php' ) ) {
        # getting song info
        require_once( './getid3/getid3.php' );
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze( $song );
        getid3_lib::CopyTagsToComments( $fileInfo );
        
        # extracting song title, or defaulting to file name
        if ( isset( $fileInfo['comments_html']['title'][0] ) && !empty( trim( $fileInfo['comments_html']['title'][0] ) ) ) {
            $title = trim( $fileInfo['comments_html']['title'][0] );
        } else {
            $title = pathinfo($song, PATHINFO_FILENAME);
        }

        # extracting song artist, or defaulting to directory name
        if ( isset( $fileInfo['comments_html']['artist'][0] ) && !empty( trim( $fileInfo['comments_html']['artist'][0] ) ) ) {
            $artist = trim( $fileInfo['comments_html']['artist'][0] );
        } else {
            $artist = str_replace( '/', ' / ', dirname( $song ) );
        }

        # extracting song album
        if ( isset( $fileInfo['comments_html']['album'][0] ) && !empty( trim( $fileInfo['comments_html']['album'][0] ) ) ) {
            $album = trim( $fileInfo['comments_html']['album'][0] );
        } else {
            $album = '';
        }

        # extracting song year/date
        if ( isset( $fileInfo['comments_html']['year'][0] ) && !empty( trim( $fileInfo['comments_html']['year'][0] ) ) ) {
            $year = trim( $fileInfo['comments_html']['year'][0] );
        } elseif ( isset($fileInfo['comments_html']['date'][0] ) && !empty( trim( $fileInfo['comments_html']['date'][0] ) ) ) {
            $year = trim( $fileInfo['comments_html']['date'][0] );
        } else {
            $year = '';
        }

        # extracting song picture
        if ( isset( $fileInfo['comments']['picture'][0] ) ) {
            $art = 'data:'.$fileInfo['comments']['picture'][0]['image_mime'].';charset=utf-8;base64,'.base64_encode( $fileInfo['comments']['picture'][0]['data'] );
        } else {
            $art = '';
        }

        return array(
            "title" => $title,
            "artist" => $artist,
            "album" => $album,
            "year" => $year,
            "art" => $art
        );
    } else {
        # defaulting to song filename and directory when getID3 is not available
        return array(
            "title" => basename( $song ),
            "artist" => dirname( $song ),
            "album" => '',
            "year" => '',
            "art" => ''
        );
    }
}


function sanitizeGet( $str ) {
    $str = stripslashes( $str );
	return $str;
}


function compareName( $a, $b ) {
    # directory name comparison for usort
    return strnatcasecmp( $a, $b );
}


function loadPage( $song = '', $error = '', $songInfo = array() ) {
    global $width, $background, $backgroundimg, $accentfg, $accentbg, $menubg, $menushadow, $gradient1, $gradient2, $filebuttonfg;

    # hiding error message div if there is no message to display
    $errorDisplay = empty( $error ) ? 'none' : 'block';

    if ( isset( $_COOKIE['nm_viewmode'] ) && $_COOKIE['nm_viewmode'] == 'playlist' ) {
        # loading playlist view
        $onLoadGoTo = "goToPlaylist('default');";
    } else {
        # loading directory view
        if ( isset( $_COOKIE['nm_currentbrowsedir'] ) ) { $dir = $_COOKIE['nm_currentbrowsedir']; }
        elseif ( isset( $_COOKIE['nm_currentsongdir'] ) ) { $dir = $_COOKIE['nm_currentsongdir']; }
        else { $dir = '.'; }
        $onLoadGoTo = "goToDir('{$dir}');";
    }

    # setting player layout depending on available information
    if ( empty( $songInfo ) ) {
        # no information means no file is playing
        $songTitle = 'No file playing';
        $songInfoalign = 'center';
        $songsrc = '';
        $pageTitle = "Music";

        # hiding info elements
        $artist = '';
        $artistDisplay = 'none';

        $album = '';
        $albumDisplay = 'none';

        $year = '';
        $yearDisplay = 'none';

        $art = '';
        $artDisplay = 'none';

        # unsetting cookies
        setcookie( 'nm_nowplaying', '-1', strtotime( '-1 day' ) );
        setcookie( 'nm_songs_currentsongdir', '-1', strtotime( '-1 day' ) );
        setcookie( 'nm_songs_active', '-1', strtotime( '-1 day' ) );
        setcookie( 'nm_songs_active_idx', '-1', strtotime( '-1 day' ) );
    } else {
        # displaying info elements where available
        $songsrc = " src=\"{$song}\"";
        $songTitle = $songInfo['title'];
        $pageTitle = $songTitle;
        if ( !empty( $songInfo['artist'] ) ) {
            $artist = $songInfo['artist'];
            $artistDisplay = 'block';
            $pageTitle = "$artist - $pageTitle";
        } else {
            $artistDisplay = 'none';
        }
        if ( !empty( $songInfo['album'] ) ) {
            $album = $songInfo['album'];
            $albumDisplay = 'block';
        } else {
            $album = '';
            $albumDisplay = 'none';
        }
        if ( !empty( $songInfo['year'] ) ) {
            $year = $songInfo['year'];
            $yearDisplay = 'inline-block';
        } else {
            $year = '';
            $yearDisplay = 'none';
        }
        if ( !empty( $songInfo['art'] ) ) {
            $art = $songInfo['art'];
            $artDisplay = 'block';
            $songInfoalign = 'left';
        } else {
            $art = '';
            $artDisplay = 'none';
            $songInfoalign = 'center';
        }
    }

    # writing page
    echo <<<HTML
<!doctype html>

<html lang="en" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="utf-8" />

    <title>{$pageTitle}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0" id="viewport" />

    <script>
        function goToDir(dir) {
            setCookie('nm_viewmode', 'browse', 7);

            // getting and displaying directory contents
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    document.getElementById('interactioncontainer').innerHTML = xmlhttp.responseText;
                }
            }
            xmlhttp.open('GET', '?dir=' + dir, true);
            xmlhttp.send();
        };

        function goToPlaylist(playlist) {
            setCookie('nm_viewmode', 'playlist', 7);

            // getting and displaying playlist contents
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    document.getElementById('interactioncontainer').innerHTML = xmlhttp.responseText;
                }
            }
            xmlhttp.open('GET', '?playlist=' + playlist, true);
            xmlhttp.send();
        };

        function addToPlaylist(song) {
            // adding song to playlist, or initialising playlist with song
            var playlist = getCookie('nm_songs_playlist');
            if (playlist) {
                // removing song if it already exists
                playlist = JSON.parse(playlist);
                var songIdx = playlist.indexOf(song);
                if (songIdx >= 0) {
                    playlist.splice(songIdx, 1);
                }
                
                // adding song to playlist
                playlist.push(song);
            } else {
                var playlist = [song];
            }
            setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
            
            // if currently playing from playlist, also updating active songlist
            var playmode = getCookie('nm_playmode');
            if (playmode == 'playlist') {
                var shuffle = getCookie('nm_shuffle');
                if (shuffle == 'on') {
                    // adding new song between current and end of current shuffled songlist
                    var currentsong = getCookie('nm_nowplaying');
                    var songlist = getCookie('nm_songs_active');
                    if (songlist) {
                        songlist = JSON.parse(songlist);
                        var songIdx = songlist.indexOf(currentsong);
                        var randomIdx = Math.floor(Math.random() * (songlist.length - songIdx) + songIdx + 1);
                        songlist.splice(randomIdx, 0, song);
                        setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                    }
                } else {
                    // getting current song's index in playlist
                    var currentsong = getCookie('nm_nowplaying');
                    var songIdx = playlist.indexOf(currentsong);

                    // setting cookies
                    setCookie('nm_songs_active', JSON.stringify(playlist), 7);
                    setCookie('nm_songs_active_idx', songIdx, 7);
                }
            }
            
        };

        function removeFromPlaylist(song) {
            var playlist = getCookie('nm_songs_playlist');
            if (playlist) {
                playlist = JSON.parse(playlist);
                var songIdx = playlist.indexOf(song);
                // moving to end if already in playlist
                if (songIdx >= 0) {
                    playlist.splice(songIdx, 1);
                }
                setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
                
                // if currently playing from playlist, also updating active songlist
                var playmode = getCookie('nm_playmode');
                if (playmode == 'playlist') {
                    var songlist = getCookie('nm_songs_active');
                    songlist = JSON.parse(songlist);
                    var currentsong = getCookie('nm_nowplaying');
                    var songIdx = songlist.indexOf(currentsong);
                    songlist.splice(songIdx, 1)
                    setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                }
                    
                // showing updated playlist
                goToPlaylist('default');
            }
        };
        
        function moveInPlaylist(song, direction) {
            var playlist = getCookie('nm_songs_playlist');
            playlist = JSON.parse(playlist);
            var songIdx = playlist.indexOf(song);
            if (songIdx + direction >= 0 && songIdx + direction < playlist.length) {
                playlist.splice(songIdx, 1);
                playlist.splice(songIdx + direction, 0, song);
            }
            setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
                
            // if currently playing from playlist, also updating active songlist
            var playmode = getCookie('nm_playmode');
            var shuffle = getCookie('nm_shuffle');
            if (playmode == 'playlist' && shuffle != 'on') {
                var currentsong = getCookie('nm_nowplaying');
                var songIdx = playlist.indexOf(currentsong);
                setCookie('nm_songs_active', JSON.stringify(playlist), 7);           
                setCookie('nm_songs_active_idx', songIdx, 7);
            }
            
            // showing updated playlist
            goToPlaylist('default');
        };
        
        function clearPlaylist() {
            setCookie('nm_songs_playlist', '', 365);
                
            var playmode = getCookie('nm_playmode');
            if (playmode == 'playlist') {
                setCookie('nm_songs_active', '', 7);                
                setCookie('nm_songs_active_idx', '0', 7);
            }
                
            goToPlaylist('default');
        };

        function setPlayMode(mode, song) {
            setCookie('nm_playmode', mode, 7);

            // switching to appropriate songlist, shuffling where necessary
            if (mode == 'browse') {
                var songlist = getCookie('nm_songs_currentsongdir');
            } else if (mode == 'playlist') {
                var songlist = getCookie('nm_songs_playlist');
            }
            if (songlist) {
                songlist = JSON.parse(songlist)
                if (getCookie('nm_shuffle') == 'on') {
                    songlist = shuffleArray(songlist);
                    
                    // moving selected song to index 0
                    var songIdx = songlist.indexOf(song);
                    songlist[songIdx] = songlist[0];
                    songlist[0] = song;                
                }
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
            }
        };

        function advance(which) {
            // requesting next/previous song and loading it
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    if (xmlhttp.responseText) {
                        window.location.href = '?play=' + xmlhttp.responseText;
                    } else if (which == 'next' && getCookie('nm_shuffle') == 'on') {
                        // end of shuffle playlist: restarting shuffle
                        toggleShuffle();
                        toggleShuffle();
                        advance('next');
                    }
                }
            }
            xmlhttp.open('GET', '?which=' + which, true);
            xmlhttp.send();
        };

        function toggleShuffle() {
            var shuffle = getCookie('nm_shuffle');
            if (shuffle == 'on') {
                // updating shuffle cookie and graphics
                setCookie('nm_shuffle', 'off', 7);
                document.getElementById('shufflebutton').classList.remove('active');

                // putting back original songlist
                var playmode = getCookie('nm_playmode');
                if (playmode == 'browse') {
                    var songlist = JSON.parse(getCookie('nm_songs_currentsongdir'));
                } else if (playmode == 'playlist') {
                    var songlist = JSON.parse(getCookie('nm_songs_playlist'));
                }

                // getting current song's index in that list
                var song = getCookie('nm_nowplaying');
                var songIdx = songlist.indexOf(song);
                
                // setting cookies
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                setCookie('nm_songs_active_idx', songIdx, 7);
            } else {
                // updating shuffle cookie and graphics
                setCookie('nm_shuffle', 'on', 7);
                document.getElementById('shufflebutton').classList.add('active');

                // randomising active songlist
                var songlist = JSON.parse(getCookie('nm_songs_active'));
                var songlist = shuffleArray(songlist);

                // getting current song's index in that list
                var song = getCookie('nm_nowplaying');
                var songIdx = songlist.indexOf(song);

                // moving it to index 0
                songlist[songIdx] = songlist[0];
                songlist[0] = song;

                // setting cookies
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                setCookie('nm_songs_active_idx', 0, 7);
            }
        };

        function shuffleArray(array) {
            var currentIndex = array.length, temporaryValue, randomIndex;

            // While there remain elements to shuffle...
            while (0 !== currentIndex) {
                // Pick a remaining element...
                randomIndex = Math.floor(Math.random() * currentIndex);
                currentIndex -= 1;

                // And swap it with the current element.
                temporaryValue = array[currentIndex];
                array[currentIndex] = array[randomIndex];
                array[randomIndex] = temporaryValue;
            }

            return array;
        };

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = 'expires=' + d.toUTCString();
            document.cookie = cname + '=' + encodeURIComponent(cvalue) + ';' + expires;
        }

        function getCookie(cname) {
            var name = cname + '=';
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for(var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    var result = c.substring(name.length, c.length);
                    result = result.replace(/\+/g, '%20');
                    return decodeURIComponent(result);
                }
            }
            return '';
        };

        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById('audio').addEventListener('error', function() {
                document.getElementById('error').innerHTML = 'Playback error';
                document.getElementById('error').style.display = 'block';
                setTimeout(function(){ advance('next'); }, 2000);
            });

            document.getElementById('audio').addEventListener('ended', function() {
                advance('next');
            });

            {$onLoadGoTo}
        }, false);
    </script>

    <style>
        html, body {
                width: 100%;
                margin: 0px; padding: 0px;
                font-family: sans-serif; }

            html {
                    background: {$background} url('{$backgroundimg}') no-repeat fixed center top;
                    background-size: cover;}

            body {
                    min-height: 100vh;
                    box-sizing: border-box;
                    padding-bottom: 5px;
                    background-color: rgba(0, 0, 0, 0.25);  }

        #stickycontainer {
                position: sticky;
                top: 0;
                margin-bottom: 10px; }

            #playercontainer {
                    padding: 20px 0;
                    background-color: #333;
                    background-image: linear-gradient({$gradient1}, {$gradient2}); }

                #player {
                        width: {$width};
                        margin: 0 auto;
                        display: flex;
                        box-sizing: border-box;
                        padding: 10px;
                        background-color: #111; }

                    #albumart {
                            display: {$artDisplay};
                            width: 7.25vw;
                            height: 7.25vw;
                            margin-right: 10px;
                            background: #333 url({$art}) center center / contain no-repeat; }

                    #song {
                            flex-grow: 1;
                            display: flex;
                            flex-direction: column;
                            justify-content: space-between; }

                        #songinfo { }

                            #songinfo div {
                                    color: grey;
                                    text-align: {$songInfoalign};
                                    font-size: 1.2vw;
                                    height: 1.4vw;
                                    width: 100%;
                                    overflow: hidden; }

                            #artist {
                                    display: {$artistDisplay}; }

                            #album {
                                    display: {$albumDisplay}; }

                            #year {
                                    margin-left: .35em;
                                    display: {$yearDisplay}; }

                                #year:before {
                                        content: "("; }

                                #year:after {
                                        content: ")"; }

                        #player audio {
                                width: 100%;
                                height: 1.3vw;
                                margin-top: 1.5vw; }

                #divider {
                        height: 2px;
                        background-color: {$accentbg}; }

        #error {
                box-sizing: border-box;
                width: {$width};
                display: {$errorDisplay};
                color: white;
                text-align: center;
                margin: 20px auto 10px auto;
                background-color: #a00;
                padding: 10px; }

        #interactioncontainer {
                box-sizing: border-box;
                line-height: 1.5; }

            #header {
                    display: flex;
                    justify-content: flex-start;
                    flex-direction: row-reverse;
                    overflow: hidden;
                    flex-wrap: wrap;
                    font-size: 0;
                    width: {$width};
                    margin: 0 auto 10px auto; }

                #playlisttitle, #breadcrumbs, #passwordrequest {
                        font-size: medium;
                        margin-top: 10px;
                        flex-grow: 1;
                        color: #333;
                        background-color: {$menubg}; }

                    #playlisttitle {
                            font-weight: bold;
                            padding: 10px; }
                            
                    #passwordrequest {
                            display: flex;
                            padding: 10px; }
                            
                    #passwordrequest form {
                            display: flex;
                            flex-grow: 1; }
                            
                        #passwordrequest #passwordinput {
                                margin: 0 10px;
                                flex-grow: 1; }

                    .breadcrumb, #breadcrumbactive {
                            display: inline-block;
                            padding: 10px; }

                    .breadcrumb:hover {
                            cursor: pointer;
                            background-color: {$menushadow}; }

                    #breadcrumbactive {
                            font-weight: bold; }

                .buttons {
                        display: flex;
                        font-size: medium;
                        margin-left: 10px;
                        margin-top: 10px; }

                    .button {
                            padding: 10px;
                            background-color: {$menubg};  }

                        .button:hover {
                                cursor: pointer;
                                background-color: {$menushadow}; }

                        .border {
                            border-right: 1px solid {$menushadow}; }

                        .active {
                                font-weight: bold;  }

                            .active span {
                                    border-bottom: 2px solid {$accentbg}; }

                .separator {
                        color: #bbb;
                        padding: 0 5px; }

            .list div {
                    width: {$width};
                    box-sizing: border-box;
                    margin: 0 auto;
                    padding: 5px 10px;
                    color: #333;
                    background-color: {$menubg};
                    border-bottom: 1px solid {$menushadow}; }

                .list div:last-child {
                        margin-bottom: 10px;
                        border: 0; }

                .list .dir:hover, .list .file:hover {
                        cursor: pointer;
                        background-color: {$menushadow};
                        font-weight: bold; }

                .list .nowplaying {
                        background-color: {$accentbg};
                        font-weight: bold; }

                    .nowplaying > div {
                            background-color: {$accentbg}; }

                    .nowplaying:hover > div {
                            background-color: {$menubg}; }

                .list .file {
                        display: flex;
                        flex-wrap: nowrap;
                        justify-content: flex-start; }


                .list .file a {
                        display: block;
                        flex-grow: 1;
                        color: #333;
                        text-decoration: none; }
                        
                .list .nowplaying a {
                        color: {$accentfg}; }

                .list .file a:active {
                        display: block;
                        color: #fff;
                        text-decoration: none; }

                .list .file .filebutton {
                        border-radius: 100%;
                        border: 0;
                        width: 25px;
                        min-width: 25px;
                        height: 25px;
                        min-height: 25px;
                        color: {$filebuttonfg};
                        text-align: center;
                        font-weight: normal;
                        margin: 0;
                        font-size: medium;
                        padding: 0;
                        display: block; }

                    .list .file .filebutton:hover {
                            color: {$accentfg};
                            background-color: {$accentbg}; }

        @media screen and (max-width: 900px) and (orientation:portrait) {
                #player, #error, #header, .list div { width: 95%; }
                #albumart { width: 24vw; height: 24vw; }
                #songinfo div { height: 5vw; font-size: 4vw; }
                #player audio { height: 5vw; }
                #playlisttitle, #breadcrumbs, .buttons, .list { font-size: small; }
        }

        @media screen and (max-width: 900px) and (orientation:landscape) {
                #stickycontainer { position: static; }
                #player, #error, #header, .list div { width: 80%; }
                #albumart { width: 12vw; height: 12vw; }
                #songinfo div { height: 2.5vw; font-size: 2vw; }
                #player audio { height: 2.5vw; }
                #playlisttitle, #breadcrumbs, .buttons, .list { font-size: small; }
        }
    </style>
</head>

<body>

<div id="stickycontainer">
    <div id="playercontainer">
        <div id="player">
            <div id="albumart"></div>
            <div id="song">
                <div id="songinfo">
                    <div id="songTitle"><b>{$songTitle}</b></div>
                    <div id="artist">{$artist}</div>
                    <div id="album">{$album}<span id="year">{$year}</span></div>
                </div>
                <div id="audiocontainer">
                    <audio id="audio" autoplay controls{$songsrc}></audio>
                </div>
            </div>
        </div>
    </div>
    <div id="divider"></div>
</div>

<div id="error">{$error}</div>
<div id="interactioncontainer"></div>

</body>
</html>
HTML;
}

?>