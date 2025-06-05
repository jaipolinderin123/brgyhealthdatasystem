import numpy as np
import pandas as pd
import sqlalchemy
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.compose import ColumnTransformer
from sklearn.metrics import silhouette_score
from dataclasses import dataclass
from typing import List, Dict, Optional
import os
from datetime import datetime
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@dataclass
class IMCIConfig:
    """Configuration for IMCI clustering analysis"""
    output_dir: str = 'imci_clustering_output'
    db_connection: str = 'mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    table_name: str = 'dispensed_medicine_imci'
    random_state: int = 42
    generate_sample: bool = False
    max_clusters: int = 6

class IMCIDataHandler:
    def __init__(self, config: IMCIConfig = IMCIConfig()):
        self.config = config
    
    def _generate_sample_data(self, n_samples: int = 1000) -> pd.DataFrame:
        """Generate sample IMCI dispensed medicine data"""
        np.random.seed(self.config.random_state)
        
        # Generate date strings for the past year
        date_strings = pd.date_range(end=pd.Timestamp.today(), periods=365).date
        date_strings = np.random.choice(date_strings, n_samples)
        
        data = {
            'date': date_strings,
            'age': np.random.randint(1, 60, n_samples),  # 1-59 months
            'gender': np.random.choice(['Male', 'Female'], n_samples, p=[0.52, 0.48]),
            'purok': np.random.choice(['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4'], n_samples),
            'chief_complain': np.random.choice(
                ['Fever', 'Cough', 'Diarrhea', 'Pneumonia', 'Malnutrition'], 
                n_samples,
                p=[0.3, 0.2, 0.25, 0.15, 0.1]
            ),
            'days': np.random.randint(1, 10, n_samples),
            'medicine_given': np.random.choice(
                ['Amoxicillin', 'Paracetamol', 'ORS', 'Zinc', 'Vitamin A'],
                n_samples,
                p=[0.3, 0.25, 0.2, 0.15, 0.1]
            ),
            'quantity': np.random.randint(1, 6, n_samples)
        }
        return pd.DataFrame(data)
    
    def fetch_data(self) -> pd.DataFrame:
        """Fetch data from database or generate sample"""
        if self.config.generate_sample:
            logger.info("Generating sample data")
            return self._generate_sample_data()
        
        try:
            engine = sqlalchemy.create_engine(self.config.db_connection)
            query = f"SELECT * FROM {self.config.table_name};"
            df = pd.read_sql(query, engine)
            logger.info(f"Successfully fetched {len(df)} records from database")
            return df
        except Exception as e:
            logger.error(f"Error fetching data from database: {str(e)}")
            logger.info("Falling back to sample data generation")
            return self._generate_sample_data()

class IMCIClusterer:
    def __init__(self, config: IMCIConfig = IMCIConfig()):
        self.config = config
        self.preprocessor: Optional[ColumnTransformer] = None
        self.model: Optional[KMeans] = None
        self.optimal_clusters: Optional[int] = None
        self.cluster_metrics: List[Dict] = []
        self.db_engine = sqlalchemy.create_engine(self.config.db_connection)
        os.makedirs(self.config.output_dir, exist_ok=True)

    def preprocess_data(self, df: pd.DataFrame) -> np.ndarray:
        """Feature selection and preprocessing for IMCI data"""
        # Select relevant features
        features = df[['age', 'gender', 'purok', 'chief_complain', 
                      'medicine_given', 'quantity', 'days']]
        
        # Define feature types
        numeric_features = ['age', 'quantity', 'days']
        categorical_features = ['gender', 'purok', 'chief_complain', 'medicine_given']
        
        # Create preprocessing pipeline
        self.preprocessor = ColumnTransformer(
            transformers=[
                ('num', StandardScaler(), numeric_features),
                ('cat', OneHotEncoder(handle_unknown='ignore'), categorical_features)
            ])
        
        return self.preprocessor.fit_transform(features)
    
    def evaluate_cluster_options(self, X: np.ndarray) -> List[Dict]:
        """Evaluate different numbers of clusters and return metrics"""
        metrics = []
        for k in range(2, self.config.max_clusters + 1):
            kmeans = KMeans(n_clusters=k, random_state=self.config.random_state)
            labels = kmeans.fit_predict(X)
            inertia = kmeans.inertia_
            silhouette = silhouette_score(X, labels)
            
            metrics.append({
                'k': k,
                'inertia': inertia,
                'silhouette_score': silhouette,
                'elbow_diff': None  # Will be calculated later
            })
        
        # Calculate elbow differences
        for i in range(1, len(metrics)):
            metrics[i]['elbow_diff'] = metrics[i-1]['inertia'] - metrics[i]['inertia']
        
        return metrics
    
    def determine_optimal_clusters(self, metrics: List[Dict]) -> int:
        """Determine optimal clusters using combined silhouette and elbow method"""
        # Find best silhouette score
        best_silhouette = max(m['silhouette_score'] for m in metrics)
        silhouette_candidates = [m for m in metrics if m['silhouette_score'] >= best_silhouette * 0.95]
        
        # Calculate average elbow difference excluding None and first k
        elbow_diffs = [m['elbow_diff'] for m in metrics[1:] if m['elbow_diff'] is not None]
        
        if not elbow_diffs:  # If all elbow_diffs are None
            return silhouette_candidates[0]['k']
        
        avg_elbow_diff = sum(elbow_diffs) / len(elbow_diffs)
        
        # Among high silhouette candidates, find the elbow point
        for m in reversed(silhouette_candidates):
            if m['elbow_diff'] is not None and m['elbow_diff'] > avg_elbow_diff:
                return m['k']
        
        return silhouette_candidates[0]['k']
    
    def save_results(self, df: pd.DataFrame, metrics: List[Dict], optimal_k: int):
        """Save results to both database and local directory"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Save cluster metrics
        metrics_df = pd.DataFrame(metrics)
        metrics_df['optimal_k'] = (metrics_df['k'] == optimal_k).astype(int)
        
        # Save to database
        metrics_df.to_sql(
            'imci_clustering_metrics',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        metrics_file = os.path.join(self.config.output_dir, f"imci_cluster_metrics_{timestamp}.csv")
        metrics_df.to_csv(metrics_file, index=False)
        
        # Save cluster assignments
        cluster_df = df.copy()
        cluster_df['cluster'] = self.get_cluster_labels(df)
        
        # Save to database
        cluster_df.to_sql(
            'imci_clustering_results',
            self.db_engine,
            if_exists='replace',
            index=False
        )
        
        # Save to CSV
        cluster_file = os.path.join(self.config.output_dir, f"imci_cluster_assignments_{timestamp}.csv")
        cluster_df.to_csv(cluster_file, index=False)
        
        logger.info(f"Results saved to database and directory: {self.config.output_dir}")
    
    def fit(self, df: pd.DataFrame):
        """Fit the clustering model with automatic cluster selection"""
        X = self.preprocess_data(df)
        self.cluster_metrics = self.evaluate_cluster_options(X)
        
        # Display evaluation metrics
        logger.info("\nIMCI Cluster Evaluation Metrics:")
        logger.info(f"{'K':<5}{'Inertia':<15}{'Silhouette':<15}{'Elbow Diff':<15}")
        logger.info("-" * 50)
        for m in self.cluster_metrics:
            logger.info(f"{m['k']:<5}{m['inertia']:<15.2f}{m['silhouette_score']:<15.4f}"
                        f"{str(m['elbow_diff'])[:7]:<15}")
        
        self.optimal_clusters = self.determine_optimal_clusters(self.cluster_metrics)
        logger.info(f"\nAutomatically selected optimal clusters: {self.optimal_clusters}")
        
        self.model = KMeans(n_clusters=self.optimal_clusters, random_state=self.config.random_state)
        self.model.fit(X)
        
        # Save results
        self.save_results(df, self.cluster_metrics, self.optimal_clusters)
        return self
    
    def get_cluster_labels(self, df: pd.DataFrame) -> np.ndarray:
        """Get cluster assignments for data"""
        X = self.preprocess_data(df)
        return self.model.predict(X)
    
    def analyze_clusters(self, df: pd.DataFrame):
        """Analyze cluster characteristics"""
        df['cluster'] = self.get_cluster_labels(df)
        
        logger.info("\nFinal IMCI Cluster Analysis:")
        logger.info("\n1. Cluster Distribution:")
        logger.info(df['cluster'].value_counts().sort_index())
        
        logger.info("\n2. Numeric Features by Cluster:")
        logger.info(df.groupby('cluster')[['age', 'quantity', 'days']].agg(['mean', 'std', 'min', 'max']))
        
        logger.info("\n3. Chief Complaints by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['chief_complain'], normalize='index').round(2))
        
        logger.info("\n4. Medicine Given by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['medicine_given'], normalize='index').round(2))
        
        logger.info("\n5. Purok Distribution by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['purok'], normalize='index').round(2))
        
        logger.info("\n6. Gender Distribution by Cluster:")
        logger.info(pd.crosstab(df['cluster'], df['gender'], normalize='index').round(2))

if __name__ == "__main__":
    # Initialize configuration
    config = IMCIConfig(
        output_dir='imci_clustering_results',
        generate_sample=False,  # Set to True to use sample data instead of database
        db_connection='mysql+mysqlconnector://ezyro_39081039:healthdata12345@sql112.ezyro.com/ezyro_39081039_healthdata'
    )
    
    # Fetch data
    data_handler = IMCIDataHandler(config)
    imci_data = data_handler.fetch_data()
    
    # Initialize and run cluster analysis
    clusterer = IMCIClusterer(config)
    clusterer.fit(imci_data)
    clusterer.analyze_clusters(imci_data)