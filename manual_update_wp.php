<?php
/**
 * Em construção! Recomendo atualização automática nativa do Wordpress! ;)
 * Para atualização consulte a página de recomendações do Wordpress
 * Faça backup da base de dados antes de atualizar
 * Não recomendo para versões muito antigas, nesses casos é recomendável fazer atualização de versão por versão. Exemplo: 4.1 para 4.2 para 4.3 para 4.4 ....
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

require("config.php");

/*
 * Separar conexão daqui depois
 */
function getPackages( $type ){

    $connection = get_connection_data();
    
    try {
        $conn = new PDO('mysql:host=' . $connection['hostname'] . ';dbname='.$connection['dbname'], $connection['username'], $connection['password']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // verificar se tabela existe 'wp_options', algumas versões mais antigas do Wordpress não possui
        $table_exists = $conn->query("SHOW TABLES LIKE '" . $connection['table_prefix'] . "sitemeta'")->rowCount() > 0;

        if($table_exists) {
            $stmt = $conn->prepare("SELECT meta_key, meta_value as option_value FROM " . $connection['table_prefix'] . "sitemeta WHERE meta_key = :meta AND site_id = 1");
        }else {
            $stmt = $conn->prepare("SELECT option_name, option_value FROM " . $connection['table_prefix'] . "options WHERE option_name = :meta");
        }

        $stmt->execute(array('meta' => '_site_transient_update_'.$type));

        $result = $stmt->fetch(PDO::FETCH_NAMED);

        $result = mbUnserialize($result["option_value"]);

        if ( count($result) )
            return $result;
        else
            return false;

    }catch(PDOException $e) {

        echo 'ERROR: ' . $e->getMessage();
        flush();
    }
}

/*
 * Corrige bug do unserialize do php com caractes especiais
 */
function mbUnserialize($string) {
//    $string = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\$
     $string = preg_replace_callback('!s:(\d+):"(.*?)";!', function($m) {
      return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
}, $string);
    return unserialize($string);
}

/*
 * Filtra as traducoes disponiveis para atualizacao
 */
function filterTranslationsForUpdate($packages) {

    if( !empty($packages->translations) ) {

        $new_packages = new ArrayObject();

        foreach ($packages->translations as $package) {
            $new_package = new stdClass();
            $new_package->slug = $package['slug']; // name folder plugin
            $new_package->package = $package['package']; // url for download
            $new_package->type = $package['type']; // plugin, theme or core
            $new_package->file_path = "downloaded/wp-content/languages/" . $package['type'] . "s/"; // directory for save

            $new_packages->append($new_package);
        }

        return $new_packages;
    }
    return false;
}

function filterPackagesForUpdate($packages, $type) {

    $new_packages = new ArrayObject();

    if( !empty($packages->response) ) {

        foreach ($packages->response as $package) {

            $new_package = new ArrayObject();

            if( $type == 'themes') {
                $new_package->slug      = $package['theme'];// name folder
                $new_package->package   = $package['package']; // url for download
                $package = $new_package;
            }

            $new_package->type      = $type; // plugin, theme or core[
            $package->file_path = 'downloaded/wp-content/'. $type;
            $new_packages->append($package);
        }
    } else if( $type == 'core' && $packages->updates[0]->response == 'upgrade' ) {

        $new_package = new ArrayObject();

        $new_package->slug = 'wordpress'; // name folder
        $new_package->package = $packages->updates[0]->download; // url for download
        $new_package->type = $type; // plugin, theme or core
        $new_package->file_path = "downloaded/"; // directory for save

        $new_packages->append($new_package);
    }else {
        echo "Nenhum {$type} encontrado para atualização!\n";
        flush();
    }

    return $new_packages;
}


function progressCallback( $resource, $download_size, $downloaded_size, $upload_size, $uploaded_size )
{

    static $previousProgress = 0;

    if ( $download_size == 0 ) {
        $progress = 0;
        $previousProgress = 0;
    }
    else
        $progress = round( $downloaded_size * 100 / $download_size );

    if ( $progress > $previousProgress)
    {
        echo "|";
        if( $progress == 100)
            echo $progress . "% \n";

        flush();
        $previousProgress = $progress;
    }
}

function extract_and_save_package($package) {

    // current directory
    if( is_writable(getcwd()) ) {

        echo "Baixando " . $package->package . " para " . $package->file_path. "\n";
        flush();

        //file_put_contents( 'progress.txt', '' );
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

            // remove o arquivo
            unlink($package->slug . ".zip");

            echo $package->slug . " extraído!\n\n";
            flush();
        } else {
            echo "Extração do arquivo falhou! \n\n";
            flush();
        }

    } else {
        echo "Diretório não gravável";
        flush();
    }
}

function updatePackages() {

    $updates = array('core', 'themes', 'plugins');

    foreach ( $updates as $update) {

        // pegar no banco os pacotes a lista de pacotes
        $packages = getPackages( $update );

        // filtrar o que deve ser atualizado
        if( is_object( $packages ) ) {
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
}

updatePackages();

?>
