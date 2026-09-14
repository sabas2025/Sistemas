<?php
interface TinyClientInterface {
  public function criarPedido(array $pedido): array;
  public function consultarPedido(string $id): array;
  public function consultarProduto(string $sku): array;
  public function atualizarEstoque(string $sku, float $qtd): array;
  public function criarProduto(array $produto): array;
  public function atualizarProduto(array $produto): array;
  public function atualizarStatusProduto(string $sku, string $situacao): array;
}
