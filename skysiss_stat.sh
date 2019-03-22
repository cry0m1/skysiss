#!/bin/bash

clear

message0=$(date | tr "\\n" "*" | tr " " "_")
message1=`w | cut -d ' ' -f 1 | grep -v USER | sort -u | tr "\n" "*" | tr " " "_"`
message2="This is `uname -s` running on a `uname -m` processor."
message3=`uptime | tr "\\n" "*" | tr " " "_"`
message4=`free | tr "\\n" "*" | tr " " "_"`
message5=`df -mh | tr "\\n" "*" | tr " " "_"`
dnsdomainame=`dnsdomainname`

status="Server date: $message0()Server Users: $message1()Server type: $message2()Server Uptime: $message3()Server RAM: $message4()FS: $message5"
#output=`curl "http://api.skysiss.com/1.0/rest/public/devstat?api_key=public&hostname=$dnsdomainame&status=$status&uh=91919028A81068D642EA63A094AF4967&response=xml&auth_token=8690c0cb14e0e85ed97cdee9d4ecc822"`
output=`curl "http://api.skysiss.com/1.0/rest/public/devstat?api_key=public&hostname=$dnsdomainame&status=$status&uh=9753379D78D8D034D372B91F6873465E&response=xml&auth_token=d5a303a7d4293566ed268e6067a1311c"`
