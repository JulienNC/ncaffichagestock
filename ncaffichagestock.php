<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ncaffichagestock extends Module
{
    public function __construct()
    {
        $this->name = 'ncaffichagestock';
        $this->tab = 'front_office_features';
        $this->version = '1.1.4';
        $this->author = 'Novema Création';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.2.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Afficher stock et message selon quantité sur fiche produit', [], 'Modules.Ncaffichagestock.Admin');
        $this->description = $this->trans('Affiche la quantité de stock ainsi qu\'un message personnalisé en fonction du stock disponible sur la page produit.', [], 'Modules.Ncaffichagestock.Admin');
        $this->confirmUninstall = $this->trans('Êtes-vous sûr de vouloir le supprimer ?', [], 'Modules.Ncaffichagestock.Admin');

    }
	public function install()
	{
        // 
		if (Shop::isFeatureActive()) {
			Shop::setContext(Shop::CONTEXT_ALL);
		}
        // Création de la table
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ncaffichagestock_ranges` (
            `id_ncaffichagestock_range` INT AUTO_INCREMENT,
            `quantity_min` INT NOT NULL,
            `quantity_max` INT NOT NULL,
            `message` TEXT NOT NULL,
            PRIMARY KEY (`id_ncaffichagestock_range`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Exécution et vérification de la requête
        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        // Installation du module et enregistrement sur les hooks 
		return parent::install() &&
			$this->registerHook('displayStockAdvanced') &&
			$this->registerHook('displayProductAdditionalInfo');
	}
	public function uninstall()
	{
        // Suppression de la table
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ncaffichagestock_ranges`');

        // Suppression du module
		return (
			parent::uninstall() 
		);
        
	}
	public function hookDisplayStockAdvanced($params)
    {
        // Récupération du produit
        $product = $params['product'] ?? null;
        if (!$product) {
            return '';
        }
        // Récupération de l'ID du produit et de l'ID l'attribut
        $id_product = (int) $product['id_product'];
        $id_product_attribute = (int) Tools::getValue('id_product_attribute', 0);

        // Récupération de la quantité disponible en stock
        $quantity = StockAvailable::getQuantityAvailableByProduct($id_product, $id_product_attribute);

        $message = '';

        // Requête SQL pour sélectionner le message en fonction de la quantité
        $sql = new DbQuery();
        $sql->select('message');
        $sql->from('ncaffichagestock_ranges');
        $sql->where('quantity_min <= '.(int)$quantity);
        $sql->where('quantity_max >= '.(int)$quantity);
        $sql->orderBy('quantity_min');
        $sql->limit('1');

        // Exécution de la requête
        $result =  Db::getInstance()->executeS($sql);
        // Si un résultat est trouvé, on récupère le message sinon message par défaut 'Aucun message configuré'
        //$message = isset($result[0]['message']) ? $result[0]['message'] : $this->trans('Aucun message configuré', [], 'Modules.Ncaffichagestock.Shop');
        // Si un résultat est trouvé, on récupère le message
        $message = isset($result[0]['message']) ? $result[0]['message'] : '';

        // Affichage HTML
        return sprintf(
            '<div class="product-stock-quantity">Stock : <b>%d</b> %s</div>',
            $quantity,
            $message
        );
        // TEST - Fichier .twig
		//return $this->render('@Modules/ncdisplaystock/views/templates/hook/displayProductAdditionalInfo.twig', [
		//	'stock_quantity' => $quantity,
		//]);
    }
    // Utilisation du nouveau système de traduction .xlf
	public function isUsingNewTranslationSystem()
	{
		return true;
	}
    public function getContent()
    {
        $output = '';
    
        // Suppression d'une tranche
        if (Tools::isSubmit('delete_range')) {
            $id = (int)Tools::getValue('id_range');
            if ($id > 0) {
                Db::getInstance()->delete('ncaffichagestock_ranges', 'id_ncaffichagestock_range = '.$id);
                $output .= $this->displayConfirmation($this->trans('Tranche supprimée avec succès.'));
            }
        }
    
        // Ajout ou de modification d'une tranche
        if (Tools::isSubmit('submitNcaffichagestock')) {
            $id = (int)Tools::getValue('id_range');
            $min = (int)Tools::getValue('quantity_min');
            $max = (int)Tools::getValue('quantity_max');
            $msg = pSQL(Tools::getValue('message'));
    
            if ($id > 0) {
                // Mise à jour d'une tranche existante
                Db::getInstance()->update('ncaffichagestock_ranges', [
                    'quantity_min' => $min,
                    'quantity_max' => $max,
                    'message' => $msg,
                ], 'id_ncaffichagestock_range = '.(int)$id);
                $output .= $this->displayConfirmation($this->trans('Tranche mise à jour avec succès.'));
            } else {
                // Insertion d'une nouvelle tranche
                Db::getInstance()->insert('ncaffichagestock_ranges', [
                    'quantity_min' => $min,
                    'quantity_max' => $max,
                    'message' => $msg,
                ]);
                $output .= $this->displayConfirmation($this->trans('Tranche ajoutée avec succès.'));
            }
        }
    
        // Récupération des tranches existantes
        $query = new DbQuery();
        $query->select('*');
        $query->from('ncaffichagestock_ranges');
        $query->orderBy('quantity_min ASC');

        $ranges = Db::getInstance()->executeS($query);
    
        // Affichage du formulaire d'ajout ou édition
        $output .= '<h3>'.$this->trans('Ajouter ou modifier une tranche').'</h3>';
        $output .= '<form method="post">';
        $output .= '<table class="table table-bordered"><thead><tr><th>'.$this->trans('Quantité min').'</th><th>'.$this->trans('Quantité max').'</th><th>'.$this->trans('Message').'</th><th colspan="2">'.$this->trans('Actions').'</th></tr></thead><tbody>';
    
        // Ligne de formulaire vide pour ajout
        $output .= '<tr>';
        $output .= '<form method="post">';
        $output .= '<td><input class="form-control" type="number" name="quantity_min" required></td>';
        $output .= '<td><input class="form-control" type="number" name="quantity_max" required></td>';
        $output .= '<td><input class="form-control" type="text" name="message" required></td>';
        $output .= '<td colspan="2"><button type="submit" name="submitNcaffichagestock" class="btn btn-success">'.$this->trans('Ajouter').'</button></td>';
        $output .= '</form>';
        $output .= '</tr>';
    
        // Affichage des tranches existantes avec formulaire d'édition + suppression
        foreach ($ranges as $range) {
            $output .= '<tr>';
            $output .= '<form method="post">';
            $output .= '<input type="hidden" name="id_range" value="'.$range['id_ncaffichagestock_range'].'">';
            $output .= '<td><input class="form-control" type="number" name="quantity_min" value="'.$range['quantity_min'].'" required></td>';
            $output .= '<td><input class="form-control" type="number" name="quantity_max" value="'.$range['quantity_max'].'" required></td>';
            $output .= '<td><input class="form-control" type="text" name="message" value="'.htmlspecialchars($range['message']).'" required></td>';
            $output .= '<td><button type="submit" name="submitNcaffichagestock" class="btn btn-primary">'.$this->trans('Mettre à jour').'</button></td>';
            $output .= '</form>';
    
            // Formulaire de suppression
            $output .= '<form method="post" onsubmit="return confirm(\''.$this->trans('Confirmer la suppression ?', [], 'Modules.Ncaffichagestock.Admin').'\');">';
            $output .= '<input type="hidden" name="id_range" value="'.$range['id_ncaffichagestock_range'].'">';
            $output .= '<td><button type="submit" name="delete_range" class="btn btn-danger">'.$this->trans('Supprimer').'</button></td>';
            $output .= '</form>';
            $output .= '</tr>';
        }
    
        $output .= '</tbody></table>';
        $output .= '</form>';
    
        return $output;
    }
}