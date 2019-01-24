<?php
header( 'content-type: text/html; charset:utf-8' );
session_start();
error_reporting(0);

/*

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

todo:
- playlist stored in cookie
  - add files from browser (after current)
  - remove files in playlist view
  - reorder files in playlist view
  - when you select a file, add all following files to playlist (?)
    - or: separate "current directory only mode" (?)
  - when audio ends: go to next file in playlist
- constrain browse= calls to not go before root (disallow .. in path?) 


N O T E S

shuffle:
- call ?shuffle=toggle
- when switching on, randomize current $song dir, set $song at index 0
  - set cookies
  - show $song dir when in browse mode, playlist when in pl mode
- when switching off, re-read current $song dir in proper order
  - set cookies
  - show $song dir when in browse mode, playlist when in pl mode

cookies:
_playmode = browse|playlist
_playlist_browse = json array of current directory's songs
_playlist_playlist = json array of current playlist
_currentdir
_activelist = list of current playlist, with _currentindex as index, instead of separate browse/playlist songlists; index enables shuffle to be toggled easily

when requesting play from browser, playmode = browse, populate _playlist_browse with rest of the directory
when requesting play from playlist, playmode = playlist

update _currentdir for every browse

AJAX call ?which=next ?which=previous to play next/previous; depending on _playmode, get from different _playlist; get ?play= url to redirect to

always browse to _currentdir if not _viewmode = playlist
    if _viewmode == playlist:
        window.onload get playlist
    elseif isset _currentdir
        window.onload browse currentdir
    elseif ?play=song
        window.onload browse song directory
    else
        window.onload browse .

    window.onload = {$onload};


themes:
$backgroundimg = background image name
eval($theme[$backgroundimg]) containing all colour variables


mobile:
menu button to show buttons?
*/

$allowedExtensions = array( 'mp3', 'flac' );
$excluded = array( '.', '..', '.htpasswd', '.htaccess', 'getid3', '.git', 'cgi-bin', 'usage', 'logs' );

$width = '60%';
$backgroundimg = 'bg.jpg';
$accent = '#fc0';

if( !empty( $_GET['play'] ) ) {
    ### playing the indicated song

    $song = sanitizeString( $_GET['play'] );

    if ( is_file( $song ) ) {
        # obtaining song info
        $songInfo = getsonginfo( $song );
        
        # setting current directory
        $dir = dirname( $song );
        
        # filling playlist with rest of the directory
        $dirContents = getDirContents( dirname( $song ) );
        foreach ($dirContents['files'] as &$file) {
            $file = dirname( $song ) . '/' . $file;
        } unset($file);
        
        # setting cookies
        setcookie( 'noctifermusic_nowplaying', $song, strtotime( '+1 day' ) );
        setcookie('noctifermusic_songlist_browse', json_encode( $dirContents['files'] ), strtotime ( '+1 week' ) );
        setcookie('noctifermusic_songlist_currentindex', array_search($song, $dirContents['files'] ), strtotime ( '+1 week' ) );
        
        # no error message
        $error = '';
    } else {
        # defaulting to root directory and displaying error message
        $songInfo = array();
        $dir = '.';
        $error = "Could not find file {$song}.";
        $song = '';
    }

    renderPage( $song, $dir, $error, $songInfo );
} elseif( !empty( $_GET['which'] ) )  {
    ### responding to AJAX request for next/previous song in songlist
    $which = sanitizeString( $_GET['which'] );
    
    if ( isset( $_COOKIE['noctifermusic_songlist_browse'] ) && isset( $_COOKIE['noctifermusic_songlist_currentindex'] ) ) {
        $songList = json_decode($_COOKIE['noctifermusic_songlist_browse'], true);
        $currentIndex = $_COOKIE['noctifermusic_songlist_currentindex'];        
        
        if ( $which === 'next' && isset( $songList[$currentIndex + 1] ) ) {
            echo $songList[$currentIndex + 1];
        } elseif ( $which === 'previous' && isset( $songList[$currentIndex - 1] ) ) {
            echo $songList[$currentIndex - 1];
        }

    }
} elseif( !empty( $_GET['dir'] ) )  {
    ### responding to AJAX request for directory contents

    $basedir = sanitizeString( $_GET['dir'] );

    if ( is_dir( $basedir ) ) {
        # listing directory contents
        $dirContents = getDirContents( $basedir );

        # returning breadcrumbs
        $breadcrumbs = explode( '/', $basedir );

        echo '<div id="header">';
        echo '<div id="breadcrumbs">';
        for ( $i = 0; $i != sizeof( $breadcrumbs ); $i++ ) {
            $title = $breadcrumbs[$i] == '.'  ? 'Root'  : $breadcrumbs[$i];

            if ($i == sizeof($breadcrumbs) - 1) {
                # current directory
                echo "<span id=\"breadcrumbactive\">{$title}</span>";
            } else {
                # previous directories with link
                $link = implode( '/', array_slice( $breadcrumbs, 0, $i+1 ) );
                echo "<span class=\"breadcrumb\" onclick=\"gotodir('{$link}');\">{$title}</span><span class=\"separator\">/</span>";
            }
        }

        echo '</div>';
        renderButtons();
        echo '</div>';

        # returning directory list
        if ( !empty( $dirContents['dirs'] ) ) {
            echo '<div id="dirlist" class="list">';
            foreach ( $dirContents['dirs'] as $dir ) {
                $link = $basedir . '/' . $dir;
                echo "<div class=\"dir\" onclick=\"gotodir('{$link}');\">{$dir}</div>";
            } unset( $dir );
            echo '</div>';
        }

        # returning file list
        if ( !empty( $dirContents['files'] ) ) {
            echo '<div id="filelist" class="list">';
            foreach ( $dirContents['files'] as $file ) {
                $link = $basedir . '/' . $file;
                if ( isset( $_COOKIE['noctifermusic_nowplaying'] ) && $_COOKIE['noctifermusic_nowplaying'] == $link ) {
                    echo "<div class=\"file nowplaying\"><a href=\"?play={$link}\">&#x25ba; {$file}</a></div>";
                } else {
                    echo "<div class=\"file\"><a href=\"?play={$link}\">&#x25ba; {$file}</a></div>";
                }
            } unset( $file );
            echo '</div>';
        }
        
        if ( empty( $dirContents['dirs'] ) && empty( $dirContents['files'] ) ) {
            echo '<div id="filelist" class="list">';
            echo '<div>This directory is empty.</div>';
            echo '</div>';
        }
    }
} else {
    ### rendering default site
    renderPage();
}


function sanitizeString( $str ) {
    $str = stripslashes( $str );
	return $str;
}


function compareName( $a, $b ) {
    # directory name comparison for usort
    return strnatcasecmp( $a, $b );
}


function renderButtons() {
   echo <<<TEST
    <div class="buttons">        
        <div class="button"><span>Load</span></div>
        <div class="border"></div>
        <div class="button"><span>Save</span></div>
    </div>
    <div class="buttons">        
        <div class="button active"><span>Shuffle</span></div>
        <div class="separator"></div>
        <div class="button active"><span>Browse</span></div>
        <div class="border"></div>
        <div class="button"><span>Playlist</span></div>
    </div>
TEST;
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


function renderPage( $song = '', $dir = '.', $error = '', $songInfo = array() ) {

    global $width, $backgroundimg, $accent;

    # hiding error message div if there is no message to display
    $errorDisplay = empty( $error ) ? 'none' : 'block';

    # setting player layout depending on available information
    if ( empty( $songInfo ) ) {
        # no information means no file is playing
        
        # unsetting cookies
        setcookie( 'noctifermusic_nowplaying', '-1', strtotime( '-1 day' ) );
        setcookie( 'noctifermusic_songlist_browse', '-1', strtotime( '-1 day' ) );
        setcookie( 'noctifermusic_songlist_currentindex', '-1', strtotime( '-1 day' ) );
        
        # hiding info elements
        $songTitle = 'No file playing';
        $songInfoalign = 'center';

        $artist = '';
        $artistDisplay = 'none';

        $album = '';
        $albumDisplay = 'none';

        $year = '';
        $yearDisplay = 'none';

        $art = '';
        $artDisplay = 'none';

        $pageTitle = "Music";
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
        function gotodir(dir){
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
        
        function advance(which){
            // playing next/previous song
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    if (xmlhttp.responseText) {
                        window.location.href = '?play=' + xmlhttp.responseText;
                    }
                }
            }
            xmlhttp.open('GET', '?which=' + which, true);
            xmlhttp.send();
        };

        window.onload = function() {
            gotodir('{$dir}');
        
            document.getElementById('audio').addEventListener('ended', function() {
                advance('next');
            });
        
            document.getElementById('audio').addEventListener('error', function() {
                document.getElementById('error').innerHTML = 'Playback error';
                document.getElementById('error').style.display = 'block';
                setTimeout(function(){ advance('next'); }, 2000);
                
            });
        }
    </script>

    <style>
        html, body {
                width: 100%;
                margin: 0px; padding: 0px;
                font-family: sans-serif; }

            html {
                    background: #bbb url('{$backgroundimg}') no-repeat fixed center top;
                    background-size: cover; }

            body {
                    min-height: 100vh;
                    box-sizing: border-box;
                    padding-bottom: 5px;
                    background-color: rgba(0, 0, 0, 0.25); }

        #stickycontainer {
                position: sticky;
                top: 0;
                margin-bottom: 10px; }

            #playercontainer {
                    padding: 20px 0;
                    background-color: #333;
                    background-image: linear-gradient(#2a2a2a, #555); }

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
                            background: #996 url({$art});
                            background-size: contain; }

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
                        background-color: {$accent}; }

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
                    justify-content: flex-end;
                    flex-wrap: wrap;
                    font-size: 0;
                    width: {$width};
                    margin: 0 auto 10px auto;
                    color: #333; }
                    
                #breadcrumbs {
                        font-size: medium; 
                        margin-top: 10px;
                        flex-grow: 1;
                        color: #333;
                        background-color: #eee;  }

                    .breadcrumb, #breadcrumbactive {
                            display: inline-block;
                            padding: 10px; }

                    .breadcrumb:hover {
                            cursor: pointer;
                            background-color: #ddd; }

                    #breadcrumbactive {
                            font-weight: bold; }
                
                .buttons {
                        display: flex;
                        margin-left: 10px;
                        margin-top: 10px; }
                        
                    .button {
                            font-size: medium;
                            padding: 10px; 
                            background-color: #eee;  }
                            
                        .button:hover {
                                cursor: pointer; 
                                background-color: #ddd; }
                            
                        .border {
                            border-right: 1px solid #ddd; }
                                                    
                        .active {
                                font-weight: bold;  }
                                
                            .active span {
                                    border-bottom: 2px solid {$accent}; }

                .separator {
                        color: #bbb;
                        padding: 0 5px; }

            .list div {
                    width: {$width};
                    box-sizing: border-box;
                    margin: 0 auto;
                    padding: 5px 10px;
                    color: #333;
                    background-color: #eee;
                    border-bottom: 1px solid #ddd; }

                .list div:last-child {
                        margin-bottom: 10px;
                        border: 0; }

                .list .dir:hover, .list .file:hover {
                        cursor: pointer;
                        background-color: #ddd;
                        font-weight: bold; }

                .list .nowplaying {
                        background-color: {$accent};
                        font-weight: bold; }

                .list .file a {
                        display: block;
                        color: #333;
                        text-decoration: none; }

                .list .file a:active {
                        display: block;
                        color: #fff;
                        text-decoration: none; }

        @media screen and (max-width: 900px) and (orientation:portrait) {
                #player, #error, #header, .list div { width: 95%; }
                #albumart { width: 24vw; height: 24vw; }
                #songinfo div { height: 5vw; font-size: 4vw; }
                #player audio { height: 5vw; }
        }
        
        @media screen and (max-width: 900px) and (orientation:landscape) {
                #stickycontainer { position: static; }
                #player, #error, #header, .list div { width: 80%; }
                #albumart { width: 12vw; height: 12vw; }
                #songinfo div { height: 2.5vw; font-size: 2vw; }
                #player audio { height: 2.5vw; }
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