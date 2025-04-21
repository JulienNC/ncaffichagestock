# ncaffichagestock
Module permettant d'afficher la quantité de stock disponible ainsi qu'un message personnalisé en fonction de cette quantité.\
Par exemple :\
pour un stock entre 10 et 20 unités, le message affiché sera "Il n'en reste pas beaucoup."\
pour un stock entre 1 et 9 unités, "Ce sont les dernières pièces !"

## Affichage sur la page produit
Vous devez modifier le fichier themes/*votre_theme*/templates/catalog/product.tpl pour y ajouter le hook suivant\
{hook h='displayStockAdvanced' product=$product}
