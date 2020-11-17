import ijson
import spacy
import io
from datetime import datetime
import fileinput
import os
import sys
from threading import Thread
import ntpath
from spacy.matcher import Matcher
from spacy.lang.en import English
from spacy.lang.en.stop_words import STOP_WORDS

class MultiRead(Thread) :
	"""Classe chargée de lire chaque fichier splitté dans un thread"""

	def __init__(self, fileToRead, pathToWrite, nlp, matcher):
		Thread.__init__(self)
		self.fileToRead = fileToRead
		self.pathToWrite = pathToWrite
		self.nlp = nlp
		self.matcher = matcher

	def run(self):
		"""Code s'exécutant quand le thread se lance"""

		processing_day = "" # Valeur par défaut
		with open(self.fileToRead, 'r', encoding='utf-8') as json_file :
			# Lecture ligne à ligne
			for line_number, line in enumerate(json_file):
				line = line.lower() # Passage de la case en minuscule pour que spaCy sur macOS fasse bien son travail (dans certains cas rares il ne parse pas des mots avec une case spécifique, ce souci n'existe pas avec Windows)
				line_as_file = io.StringIO(line) # Hack permettant à ijson de parser le fichier mal formaté (JSON fourni non valide)
				json_parser = ijson.parse(line_as_file) # Un nouveau parser par ligne
				writeLine = False
				line_to_write = ""

				# Parcours classique ijson
				for prefix, type, value in json_parser:
					if prefix == "created_at":
						# Si vide on commence notre lecture
						if processing_day == "":
							processing_day = datetime.strptime(value, "%Y-%m-%d %H:%M:%S")

							# Création d'un dossier par jour avec un document au même nom que le fichier splitté lu
							filepath = self.pathToWrite + "/" + processing_day.strftime("%Y-%m-%d") + "/"
							#filepath = self.pathToWrite + "/" + processing_day.strftime("%Y-%m-%d/%H") + "/"
							filename = ntpath.basename(self.fileToRead) + ".txt"

							# Si le dossier n'existe pas on le créé
							if not os.path.exists(filepath):
								os.makedirs(filepath)

							self.w = open(filepath + filename, "a") # On créé ce fichier et on l'ouvre en mode "append"

						current_day = datetime.strptime(value, 	"%Y-%m-%d %H:%M:%S") # On récupère le jour de la ligne lue

						#if current_day.date() == processing_day.date() and current_day.time().hour == processing_day.time().hour : # Comparaison date & heure
						if current_day.date() == processing_day.date() : # Comparaison date seule
							writeLine = True # On peut écrit dans le même fichier
						# Sinon on écrit dans le fichier et on passe à un autre jour (autre dossier)
						else :
							#print("Fin lecture journée")
							self.w.close()
							processing_day = ""
							line_number -= 1 # Retour à la ligne précédente pour l'écrire dans le fichier suivant
							continue

					if prefix == "text" :
						# On n'écrit pas dans notre fichier les mots à 3 caractères, évite de lancer le matcher (gain en performances)
						if writeLine and len(value) > 3 :
							writeLine = False # On réécrira dans le fichier uniquement si il y a une autre ligne après qui tombe au même jour

							doc = self.nlp(value)
							matches = self.matcher(doc)
							for match_id, start, end in matches:
								line_to_write += doc[start:end].lemma_ + " "
							self.w.write(line_to_write + "\n")
		print("Un thread a fini son job :-)")
		return # Le thread a fini son travail

def getFilesInPath(path) :

	listOfFiles = []

	for f in os.listdir(path) :
		# Ignore the .DS_Store in MacOS which causes bugs
		if not f.startswith('.'):
			listOfFiles.append(f)

	return listOfFiles

threads = []
readPath  = sys.argv[1]
writePath = "/Users/alexy/DM/output"
filesToRead = getFilesInPath(readPath)

nlp = spacy.load("en_core_web_sm", disable=['parser', 'tagger', 'textcat', 'ner', 'entity_ruler', 'sentencizer', 'merge_entities', 'merge_subtokens', 'merge_noun_chunks', 'sentencizer'])
matcher = Matcher(nlp.vocab)
pattern = [{"LENGTH": {">":1},"IS_STOP": False, "IS_PUNCT": False,"IS_DIGIT": False, "LIKE_NUM": False,
	 "LIKE_URL": False, "LIKE_EMAIL":False, "IS_ALPHA":True,
	 "ORTH": {"NOT_IN": ["lol","jk","lmao","wtf","wth","rofl","stfu","lmk","ily"
						  ,"yolo","smh","lmfao","rt","retweete","nvm", "ikr"
						  ,"ofc","btw","jst","donald","trump","joe","biden"
						  ,"a", "about", "all", "also", "and", "as", "at", "be", "because", "but", "by", "can"
						  , "come", "could", "day", "do" , "even", "find", "first", "for", "from", "get"
						  , "give", "go", "have", "he", "her", "here", "him", "his", "how" ,"I", "if", "in"
						  , "into", "it", "its", "just", "know", "like", "look", "make", "man", "many", "me"
						  , "more", "my", "new", "no", "not", "now", "of", "on", "one", "only", "or", "other"
						  , "our", "out", "people", "say", "see", "she", "so", "some", "take", "tell", "than"
						  , "that", "the", "their", "them", "then", "there" "these", "they", "thing", "think"
						  , "this", "those", "time", "to", "two", "up", "use", "very", "want", "way", "we", "well"
						  , "what", "when", "which", "who", "will", "with", "would", "year", "you", "your"
              ]}}]
matcher.add("BIGRAMS_PATTERN", None, pattern)

# Création des threads
for i in range (0, len(filesToRead)) :
	threads.append(MultiRead(readPath + filesToRead[i], writePath, nlp, matcher))

# Lancement des threads
for i in range (0, len(threads)) :
	threads[i].start()

# Attente qu'ils aient fini
for i in range (0, len(threads)) :
	threads[i].join()

print("Le travail est terminé :-)")
quit()
