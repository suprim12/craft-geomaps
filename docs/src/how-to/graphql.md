# Querying in GraphQL

The GraphQL query input supports all the same parameters as regular [Searching](/getting-started/usage#searching), with one exception: the `location` parameter only accepts a string value. To search by lat/lng coordinates, use the `coordinate` input instead.

```graphql
{
  entries (
    map: {
      unit: Kilometres
      location: "Maidstone, Kent"
      country: "UK"
      radius: 10
      coordinate: {
        lat: 51.27136675686769
        lng: 0.4939985275268555
      }
    }
    section: "locations"
    orderBy: "distance"
  ) {
    title
    ... on locations_locations_Entry {
      map {
        lat
        lng
        distance
        zoom
        address
        parts {
          number
          address
          city
          postcode
          county
          state
          country
        }
      }
    }
  }
}
```

::: tip
The `distance` field in the response is only populated when you perform a location search using the `location` or `coordinate` inputs.
:::
