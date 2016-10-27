#!/bin/bash
# manual_update_wp.sh

######### PEGA VARIÁVEIS ############
DEFAULT_ORIGIN=`pwd`
DEFAULT_BACKUP='/home/backups'
SCRIPT_PWD=`dirname $0`

echo "Digite a origem:"
echo "[padrão: $DEFAULT_ORIGIN]"
read DIR_ORIGIN

if [ -z $DIR_ORIGIN ]; then
    DIR_ORIGIN=$DEFAULT_ORIGIN
fi
if [ ! -d "$DIR_ORIGIN" ]; then
    echo "$DIR_ORIGIN não é um diretório válido ou não existe."
    echo "Criar (S/n)?"
    read CRIAR_ORIGIN
    if [[ $CRIAR_ORIGIN = "s" ]]; then
	mkdir -p $DIR_ORIGIN
    elif [[ $CRIAR_ORIGIN = "n" ]]; then
	exit 0
    else
	# acao padrao
	mkdir -p $DIR_ORIGIN
    fi
fi

echo "Digite o diretório de backup:"
echo "[padrão: $DEFAULT_BACKUP]"
read DIR_BACKUP
if [ -z $DIR_BACKUP ]; then
    DIR_BACKUP=$DEFAULT_BACKUP
fi
if [ ! -d "$DIR_BACKUP" ]; then
    echo "$DIR_BACKUP não é um diretório válido ou não existe."
    echo "Criar (S/n)?"
    read CRIAR_BACKUP
    if [[ $CRIAR = "s" ]]; then
	mkdir -p $DIR_BACKUP
    elif [[ $CRIAR_BACKUP = "n" ]]; then
	exit 0
    else
	# acao padrao
	mkdir -p $DIR_BACKUP
    fi    
fi

# data para o backup
DATA=`/bin/date +%Y%m%d%H%M`

####
######
######### Fim variáveis #########

# fazer download dos arquivos
echo -en "- Fazer download dos temas, plugins e core (y/n)? "
read ANSWER1
if echo "$ANSWER1" | grep -iq "^y" ;then
    if [ ! -d "$DIR_ORIGIN/downloaded" ]; then
	mkdir $DIR_ORIGIN/downloaded
    else
	rm -r $DIR_ORIGIN/downloaded
    fi
    
    ## baixa plugins desatualizados
    php $SCRIPT_PWD/manual_update_wp.php
fi

# apos baixar todas os pacotes, perguntar para o usuario se deseja atualizar
echo -en "\nAtenção!!!\n- Verifique se os arquivos para cópia estão corretos.\n- Antes de copiar será feito backup dos arquivos.\n- Deseja copiar os arquivos para "$DIR_ORIGIM" (y/n)? "
read ANSWER2
if echo "$ANSWER2" | grep -iq "^y" ;then
    
    # BACKUP
    echo -en "Fazer backup do wordpress "$DIR_ORIGIN" (y/n)? "
    read ANSWER_BACKUP
    if echo "$ANSWER_BACKUP" | grep -iq "^y" ;then
	
	# Cria o diretório para backup
	mkdir -p $DIR_BACKUP/$DATA/
	
	# Faz uma copia do wordpress atual exceto blogs.dir
	echo "Fazendo backup do wordpress "$DIR_ORIGIN
	
	rsync -av --progress $DIR_ORIGIN $DIR_BACKUP/$DATA/. --exclude blogs.dir
	echo "Backup realizado!"
    fi
    # FIM BACKUP
    
    
    # copiar para o diretorio
    echo "Copiando arquivos para "$DIR_PATH
    
    cp -avr $DIR_ORIGIN/downloaded/wordpress/* $DIR_ORIGIN
    
    echo "Atualizacao wordpress ok"
    
    # atualizar plugins e temas
    echo "Atualizando plugins, temas e traduções!"
    cp -avr $DIR_ORIGIN/downloaded/wp-content/* $DIR_ORIGIN/wp-content/.
    echo "Plugins, temas e traduções atualizados!"
    
    
    echo "Atualizacao finalizada! :)"
    
else
    echo "Atualização cancelada!"
    exit
fi
