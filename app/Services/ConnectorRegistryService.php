<?php
/** V104.16 - Registro de conectores plugáveis reais. */
class ConnectorRegistryService {
  /** @return ConnectorInterface[] */
  public static function all(): array {
    return [
      new TinyConnector(),
      new VsmConnector(),
      new BlingConnector(),
      new OmieConnector(),
      new MercadoLivreConnector(),
      new ShopeeConnector(),
      new AmazonConnector(),
      new TikTokShopConnector(),
    ];
  }
  public static function catalog(): array {
    return array_map(function(ConnectorInterface $c){ return [
      'codigo'=>$c->code(), 'nome'=>$c->name(), 'categoria'=>$c->category(), 'status'=>$c->status(),
      'capabilities'=>$c->capabilities(), 'requirements'=>$c->requirements(), 'health'=>$c->health()
    ]; }, self::all());
  }
  public static function find(string $code): ?ConnectorInterface {
    foreach (self::all() as $c) if ($c->code() === $code) return $c;
    return null;
  }
}
