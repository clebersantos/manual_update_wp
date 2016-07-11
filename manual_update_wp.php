<?php
/**
 * Em construção! Recomendo atualização automática nativa do Wordpress! ;)
 *
 *
 * @package manual_update_wp
 */

/**
 * Description: Baixar todos os temas, plugins e traduções disponíveis para atualização..
 * Author:      Cleber Santos
 * Author URI:  https://culturadigital.br/clebersantos/
 * Version:     0.1
 * License:     GPLv2 or later (license.txt)
 */
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ob_end_clean();

/*
 * Separar conexão daqui depois
 */
function getPackages( $type ){

    // Variables to connect
    $username = 'usuario';
    $password  = 'senha';
    $dbname = 'wordpress';

    try {
        $conn = new PDO('mysql:host=localhost;dbname='.$dbname, $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare('SELECT meta_key, meta_value FROM wp_sitemeta WHERE meta_key = :meta AND site_id = 1');
        $stmt->execute(array('meta' => '_site_transient_update_'.$type));

        $result = $stmt->fetch(PDO::FETCH_NAMED);

        $result = mbUnserialize($result["meta_value"]);

        if ( count($result) )
            return $result;
        else
            return false;

    }catch(PDOException $e) {
        return 'ERROR: ' . $e->getMessage();
    }
}

/*
 * Corrige bug do unserialize do php com caractes especiais
 */
function mbUnserialize($string) {
    $string = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $string);
    return unserialize($string);
}

/*
 * Filtra as traducoes disponiveis para atualizacao
 */
function filterTranslationsForUpdate($packages) {

//    $packages = getPackages($type);

    if( !empty($packages->translations) ) {

        $new_packages = new ArrayObject();

        foreach ($packages->translations as $package) {
            $new_package = new stdClass();
            $new_package->slug      = $package['slug']; // name folder plugin
            $new_package->package  = $package['package']; // url for download
            $new_package->type      = $package['type']; // plugin, theme or core
            $new_package->file_path = "languages/" . $package['type'] . "s/"; // directory for save

            $new_packages->append($new_package);

        }

        return $new_packages;
    }

    return false;
}

function filterPackagesForUpdate($packages, $type) {
    //ob_implicit_flush(true);
//    $packages = getPackages($type);

    if( !empty($packages->response) ) {

        $new_packages = new ArrayObject();

        foreach ($packages->response as $package) {

            $package->file_path = $type . '/';
            $new_packages->append($package);
        }
        return $new_packages;

    } else {
        echo "Nenhum {$type} para traducao  encontrado! \n";
        flush();
    }

    return false;
}


function progressCallback( $resource, $download_size, $downloaded_size, $upload_size, $uploaded_size )
{
    //ob_implicit_flush(true);

    static $previousProgress = 0;

    if ( $download_size == 0 ) {
        $progress = 0;
        $previousProgress = 0;
    }
    else
        $progress = round( $downloaded_size * 100 / $download_size );
//        $progress = $downloaded_size / $download_size * 100;

    if ( $progress > $previousProgress)
    {
        echo $progress . "% ";
        flush();
        $previousProgress = $progress;
//        $fp = fopen( 'progress.txt', 'a' );
//        fputs( $fp, "$progress\n" );
//        fclose( $fp );
    }
}

function extract_and_save_package($package) {
    //ob_implicit_flush(true);

    // current directory
    if( is_writable(getcwd()) ) {

        // cria o diretório
        if( !file_exists($package->file_path))
            mkdir($package->file_path);

        echo "Baixando " . $package->package . " para " . $package->file_path. "\n";
        flush();

        // baixa o arquivo
//        file_put_contents( $package->slug . ".zip", fopen($package->package, 'r'));

        file_put_contents( 'progress.txt', '' );
        $targetFile = fopen( $package->slug . ".zip", 'w' );
        $ch = curl_init( $package->package );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $ch, CURLOPT_NOPROGRESS, false );
        curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, 'progressCallback' );
        curl_setopt( $ch, CURLOPT_FILE, $targetFile );
        curl_exec( $ch );
        fclose( $targetFile );

        // extraindo para o diretório correto
        $zip = new ZipArchive;
        if ($zip->open($package->slug . ".zip") === TRUE) {

            $zip->extractTo($package->file_path);
            $zip->close();

            // apaga o arquivo
            unlink($package->slug . ".zip"); //remove arquivo

            echo $package->slug . " extraído!\n";
            flush();
        } else {
            echo "Extração do arquivo falhou! \n";
            flush();
        }

    } else {
        echo "Diretório não gravável";
        flush();
    }
}

function updatePackages() {


    $updates = array('themes', 'plugins', 'core');

    foreach ( $updates as $update) {

        // pegar no banco os pacotes a lista de pacotes
        $packages = getPackages( $update );

        // filtrar o que deve ser atualizado
        $packages_for_update = filterPackagesForUpdate($packages, $update );

        // loop com os pacotes para atualizacao
        if ( is_object( $packages_for_update )) {

            foreach ( $packages_for_update as $package ) {
                extract_and_save_package($package);

            }

        }

        // salvar as traducoes disponiveis deste tipo de pacote, se é plugin ou tema
        $translations = filterTranslationsForUpdate($packages);

        if ( is_object( $translations ) ) {

            foreach ($translations as $translate) {

                extract_and_save_package($translate);

            }
        }

    }
}


updatePackages();

?>
