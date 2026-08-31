CLEAR
@ 0,0 SAY "                              Legende                                          "
@ 1,0 SAY "     --------------------------------------------------------------"
@ 2,0 SAY "                                                               "
@ 3,1 SAY "     Cyl = Cylinder = Tracks = Spuren                               "
@ 4,1 SAY "     HDS = Heads = Kîpfe                                            "
@ 5,1 SAY "     Sec = Sectors = Sektoren pro Spur                              "
@ 6,1 SAY "     Cap = Capacity, angegebene Grîsse in MB (genaue Rechnung unbekannt)      "
@ 7,1 SAY "     MB  = errechnete Grîsse in MB nach folgender Rechnung     :"
@ 8,1 SAY "     MB  = (Sektoren * 512 Byte * Kîpfe * Spuren / (1024*1024))     "
@ 9,1 SAY "                                                               "
@ 10,1 SAY "    Im Menue 'Biostyp suchen' wird versucht den Biostyp zu finden, "
@ 11,1 SAY "    der fÅr eine AT-Bus Platte den geringsten Verlust ergibt.      "
@ 13,1 SAY "    Im Menue 'Typ suchen' genÅgt ein Teil der Typenbezeichnung ;   "
@ 14,1 SAY "    bei Eingabe von  '20' als Typ werden sÑmtliche Typen aufge-    "
@ 15,1 SAY "    listet bei denen ein '20' in der Typbezeichnung vorkommt.     "
@ 17,1 SAY "    Leider ist der Suchvorgang dadurch ziemlich langsam, aber      "
@ 18,1 SAY "    besser langsam, als gar keine Daten :-)                        "
@ 19,1 SAY "                                                               "

@ 22,5 say ""

wait
goto top
return


















