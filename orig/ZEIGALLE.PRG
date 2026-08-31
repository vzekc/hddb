CLEAR 
USE diskbank
set talk off
auswahl= " "
dummy=" "
dummyb=" "
a=-1
b=0
c=0
d=0
gez=0
GOTO TOP
                do while .not. eof()
                        if produzent=dummy 
                        gez=gez+1
                        @ a ,b+16 say ltrim(str(gez))
                        skip
                         
                         else
                          if a = 20
                           a=-1
                           b=b+20
                                if b > 70
                                        b=0
                                        set color to r
                                        wait
                                        set color to w
                                        @ 21,0 clear

                                endif
                          endif
                        
                         a=a+1
                         gez=0
                         c=c+1
                         @ a,b say Produzent 
                         dummy=produzent
                          
                        endif
                enddo                
@ 23, 0 
?? "gefundene Hersteller" , c
wait
goto top
return


