# cat /tmp/count_words.sh
#!/bin/bash
#Desc: Find out frequency of words in a file

if [ $# -ne 2 ];
then
  echo "Usage: $0 filename $1 date";
  exit -1
fi

filename=$1
egrep -o "\b[[:alpha:]]+\b" $filename | \

awk -v date=$2 '{ count[$0]++ }

END {
  PROCINFO["sorted_in"] = "@val_num_desc"
  for(k in count) {
    print "INSERT INTO `lemma_count` SET tweet_date = \047"date "\047, word = \047"k "\047, occurences = \047" count[k] "\047;";
  }
}'
