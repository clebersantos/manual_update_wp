#!/bin/bash
# manual_update_wp.sh

######### Variáveis ############
######
###

	# data para o backup
	DATA=`/bin/date +%Y%m%d%H%M`

	# diretorio que os arquivos serao salvos
	DIR_ORIGIN=$PWD

###
######
######### Altere aqui para o seu diretorio ########
######
###
	# nome do site
	SITE_NAME="buddypress"

	# diretório do site para backup e atualizar
	SITE_PATH='/var/www/buddypress/' # coloque a barra no final ex: /var/www/cdigital/

####
######
######### Fim variáveis #########

# fazer download dos arquivos
echo -en "- Fazer download dos temas, plugins e core (y/n)? "
read answer1
if echo "$answer1" | grep -iq "^y" ;then
   rm -r downloaded
   php $PWD'/manual_update_wp.php' 
fi

# apos baixar todas os pacotes, perguntar para o usuario se deseja atualizar
echo -en "\nAtenção!!!\n- Verifique se os arquivos para cópia estão corretos.\n- Antes de copiar será feito backup dos arquivos.\n- Deseja copiar os arquivos para "$SITE_PATH" (y/n)? "
read answer
if echo "$answer" | grep -iq "^y" ;then
   
    # BACKUP
    echo -en "Fazer backup do wordpress "$SITE_NAME" (y/n)? "
    read answer_backup
    if echo "$answer_backup" | grep -iq "^y" ;then
	    
		# Cria o diretório para backup
		mkdir -p $DIR_ORIGIN/backups/$SITE_NAME/$DATA/

		# Faz uma copia do wordpress atual exceto blogs.dir
		echo "Fazendo backup do wordpress "$SITE_NAME

		#echo $DIR_ORIGIN/backups/$SITE_NAME/$DATA/.
		# MUITO CUIDADO COM O RSYNC
		rsync -av --progress $SITE_PATH $DIR_ORIGIN/backups/$SITE_NAME/$DATA/. --exclude blogs.dir
		echo "Backup realizado!"
	fi
	# FIM BACKUP


	# copiar para o diretorio
	echo "Copiando arquivos para "$SITE_PATH

	cp -avr $DIR_ORIGIN/downloaded/wordpress/* $SITE_PATH.

	echo "Atualizacao wordpress ok"

	# atualizar plugins e temas
	echo "Atualizando plugins, temas e traduções!"
	cp -avr $DIR_ORIGIN/downloaded/wp-content/* $SITE_PATH/wp-content/.
	echo "Plugins, temas e traduções atualizados!"


	echo "Atualizacao finalizada! :)"

else
    echo "Atualização cancelada!"
    exit
fi