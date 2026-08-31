CLEAR
SET TALK OFF
SET STATUS OFF
B=512
S=17
H=0
C=0

@ 2,2 say " Berechnung der effektiven Gr”sse einer Festplatte :"
@ 4,2 say " Sie ben”tigen mindestens 3 Daten : Cylinder, Heads und Sectors per Track"

@ 6,3 SAY "SECTORS/TRACK "
@ 6,18 GET S
READ 

@ 7,3 SAY "HEADS"
@ 7,18 GET H
READ

@ 8,3 SAY "CYLINDER"
@ 8,18 GET C
READ

@ 9,3 SAY "BYTE/SEKTOR"
@ 9,18 GET B
READ

MEGS= LTRIM(STR(B*S*H*C / 1048576 ,,2))
@ 6,35 say "MBYTE (1024*1024 BYTE)"  
@ 6,60 SAY MEGS

@ 22,0 say ""
wait 
goto top
return

