<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerPeriodicNotifications implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct() {
    // job dispatched from app/Console/Kernel.php
  }
  
  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle() {
    dump("TriggerPeriodicNotifications");
    
    // 5 giorni prima di ricapitalizzazione - ricordare che ci sono brite non ancora sbloccati
    // 1 giorno prima di ricapitalizzazione - ricordare che ci sono brite non ancora sbloccati
    
    // Ogni mese - Se utente non più premium, ricorda che non potrà sbloccare i brite
    
    // dopo ogni chiusura semestre, informa l'utente di eventuali brite aggiungi nel wallet
    
    
    /*
     * creare notifica che informa dei nuovi brite disponibili
      Se non li ho sbloccati, rimandare una notifica 5 giorni prima della ricapitalizzazione per ricordarlo.
      Rifarlo anche 1 giorno prima
    
    TUTTO QUESTO, mensilmente, deve controllare se l'utente è ancora premium.
     Se non lo è più, non li può sbloccare (Riceve una notifica comunque, ma lo invita ad attivare il pack premium per sbloccare i brite.)
     */
    
    
    // add a table row for CreateNotification job handled by the news app
  }
}
